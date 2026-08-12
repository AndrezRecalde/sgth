<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Illuminate\Support\Facades\DB;

class MovimientoPersonalStateService
{
    public function __construct(
        private readonly ContratoServidorService $contratoServidorService,
        private readonly FirmanteAccionPersonalService $firmanteService,
        private readonly SubrogacionService $subrogacionService,
    ) {
    }


    /**
     * Grafo de transiciones permitidas. No es "cualquier estado hacia
     * adelante": REGISTRADA solo se alcanza desde SUSCRITA, y ANULADA
     * solo desde estados anteriores a REGISTRADA. NOTIFICADA y ANULADA
     * son terminales.
     */
    private const TRANSICIONES = [
        'borrador'   => ['suscrita', 'anulada'],
        'suscrita'   => ['registrada', 'anulada'],
        'registrada' => ['notificada'],
        'notificada' => [],
        'anulada'    => [],
    ];

    public function transicionar(
        MovimientoPersonal $movimiento,
        EstadoAccionPersonal $destino,
        array $datos = []
    ): MovimientoPersonal {
        $origen = $movimiento->estado;

        $this->assertTransicionPermitida($origen, $destino);

        return DB::transaction(function () use ($movimiento, $destino, $datos) {
            match ($destino) {
                EstadoAccionPersonal::SUSCRITA   => $this->aplicarSuscrita($movimiento, $datos),
                EstadoAccionPersonal::REGISTRADA => $this->aplicarRegistro($movimiento, $datos),
                EstadoAccionPersonal::NOTIFICADA => $this->aplicarNotificacion($movimiento, $datos),
                EstadoAccionPersonal::ANULADA    => $this->aplicarAnulacion($movimiento, $datos),
                default                          => $this->aplicarDictamenSiViene($movimiento, $datos),
            };

            $movimiento->estado = $destino;
            $movimiento->save();

            return $movimiento->fresh();
        });
    }

    /**
     * Un movimiento en REGISTRADA o NOTIFICADA no se edita: crea uno nuevo
     * que referencia al original vía corrige_a_id, copiando los campos
     * relevantes para que el usuario solo edite lo que cambió. El
     * original permanece visible y sin cambios.
     */
    public function corregir(MovimientoPersonal $original, array $cambios): MovimientoPersonal
    {
        if (!in_array($original->estado, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)) {
            throw new ReglaNegocioException(
                'Solo se puede corregir un movimiento en estado registrada o notificada.'
            );
        }

        $camposCopiables = [
            'servidor_id', 'tipo_movimiento', 'subtipo_movimiento',
            'requiere_dictamen_medico', 'movimiento_previo_id',
            'categoria', 'codigo', 'descripcion',
            'fecha_efectiva', 'fecha_inicio', 'fecha_fin',
            'unidad_origen_id', 'unidad_destino_id', 'puesto_origen_id', 'puesto_destino_id',
            'resolucion_numero', 'documento_respaldo', 'observacion', 'lugar_trabajo',
            'caucionado', 'caucion_numero', 'caucion_fecha',
        ];

        $datosBase = collect($original->getAttributes())->only($camposCopiables)->toArray();

        $datos = array_merge($datosBase, $cambios, [
            'corrige_a_id'                => $original->id,
            'estado'                      => EstadoAccionPersonal::BORRADOR,
            'codigo_registro'             => null,
            'fecha_registro'              => null,
            'dictamen_presupuestario_ref' => null,
            'notificado_por'              => null,
            'fecha_notificacion'          => null,
            'autorizado_por'              => auth()->id(),
        ]);

        return MovimientoPersonal::create($datos);
    }

    private function assertTransicionPermitida(EstadoAccionPersonal $origen, EstadoAccionPersonal $destino): void
    {
        $permitidas = self::TRANSICIONES[$origen->value] ?? [];

        if (!in_array($destino->value, $permitidas, true)) {
            throw new ReglaNegocioException(
                "No se puede pasar de '{$origen->etiqueta()}' a '{$destino->etiqueta()}'."
            );
        }
    }

    private function aplicarDictamenSiViene(MovimientoPersonal $movimiento, array $datos): void
    {
        if (!empty($datos['dictamen_presupuestario_ref'])) {
            $movimiento->dictamen_presupuestario_ref = $datos['dictamen_presupuestario_ref'];
        }
    }

    /**
     * Si el tipo de movimiento tiene efecto económico (Art. 105 LOSEP):
     * exige dictamen_presupuestario_ref y disponibilidad verificada en la
     * partida del puesto involucrado antes de poder suscribirse.
     */
    private function aplicarSuscrita(MovimientoPersonal $movimiento, array $datos): void
    {
        $this->aplicarDictamenSiViene($movimiento, $datos);

        // Suscribir es el acto de firma: aquí se sella quién firmó, porque las
        // autoridades rotan y el documento debe seguir mostrando a quien firmó
        // entonces aunque hoy el cargo lo ocupe otra persona.
        $this->firmanteService->sellarEn(
            $movimiento,
            $datos['fecha_suscripcion'] ?? null
        );

        $this->solicitarCertificacionMedicaSiHaceFalta($movimiento);

        if (!$movimiento->tipo_movimiento->tieneEfectoEconomico()) {
            return;
        }

        if (empty($movimiento->dictamen_presupuestario_ref)) {
            throw new ReglaNegocioException(
                'Este movimiento tiene efecto económico: requiere un dictamen presupuestario antes de suscribirse.'
            );
        }

        $puesto = $movimiento->puestoDestino ?? $movimiento->puestoOrigen ?? $movimiento->servidor?->puesto;
        $partida = $puesto?->partidaPresupuestaria;

        if (!$partida || !$partida->disponible) {
            throw new ReglaNegocioException(
                'No hay disponibilidad presupuestaria verificada para el puesto de este movimiento.'
            );
        }
    }

    /**
     * Al llegar a REGISTRADA, los tipos que crean o modifican un vínculo
     * (creaVinculo() / modificaVinculo()) materializan ese vínculo recién
     * aquí — antes de esto, todo lo propuesto vivía solo en las columnas
     * del propio MovimientoPersonal (tipo_nombramiento_propuesto,
     * remuneracion_propuesta, puesto_destino_id, unidad_destino_id).
     */
    private function aplicarRegistro(MovimientoPersonal $movimiento, array $datos = []): void
    {
        // Los datos del vínculo llegan con la transición, no por edición: una
        // acción suscrita ya no se edita, pero sí se completa en el acto de
        // aprobarla, que es cuando Talento Humano tiene a la mano el número de
        // contrato, la resolución y la remuneración pactada.
        $this->aplicarDatosVinculo($movimiento, $datos);

        $this->validarDatosPropuestos($movimiento);
        $this->validarDictamenMedico($movimiento);

        $movimiento->codigo_registro = $this->generarCodigoRegistro();
        $movimiento->fecha_registro  = now()->toDateString();

        $tipo = $movimiento->tipo_movimiento;

        if ($tipo->creaVinculo()) {
            // El vínculo vigente ya se descartó en validarDatosPropuestos().
            $this->contratoServidorService->crear($movimiento->servidor_id, [
                'tipo_nombramiento'        => $movimiento->tipo_nombramiento_propuesto->value,
                'numero_contrato'          => $movimiento->numero_contrato,
                'unidad_administrativa_id' => $movimiento->unidad_destino_id,
                'puesto_id'                => $movimiento->puesto_destino_id,
                // Si el ingreso es un reemplazo, el contrato hereda el enlace:
                // es lo que consulta el control de plazas para no contar dos
                // veces la misma, y lo que permite listar quién cubre a quién.
                'cubre_movimiento_id'      => $movimiento->cubre_movimiento_id,
                'fecha_inicio'             => $movimiento->fecha_efectiva?->toDateString(),
                // Solo para vínculos con plazo pactado; si va null, Servicios
                // Profesionales recibe igualmente su vencimiento derivado en
                // ContratoServidorService.
                'fecha_fin'                => $movimiento->fecha_fin_propuesta?->toDateString(),
                'remuneracion'             => $movimiento->remuneracion_propuesta,
                'resolucion_numero'        => $movimiento->resolucion_numero,
                // null = la acción no se pronunció: el contrato conserva su
                // propio default en vez de forzarle un false.
                ...($movimiento->puede_marcar === null
                    ? []
                    : ['puede_marcar' => $movimiento->puede_marcar]),
                'estado'                   => 'vigente',
            ], $movimiento);
        } elseif ($movimiento->subtipoEfectivo()?->modificaPuesto()) {
            // Traslado y traspaso reubican dentro del mismo vínculo. Las
            // comisiones, que comparten el tipo paraguas, no entran aquí: son
            // ausencias temporales y el servidor conserva su puesto.
            $this->contratoServidorService->reestructurarDesdeMovimiento($movimiento);
        } elseif ($movimiento->subtipoEfectivo()?->cierraVinculo()) {
            $this->cerrarVinculo($movimiento);
        }

        // La subrogación no crea vínculo: reemplaza temporalmente al titular
        // en su puesto. Recién aquí surte efecto, y con ella la facultad de
        // firmar que FirmanteAccionPersonalService le reconoce al subrogante.
        if ($tipo === TipoMovimientoPersonal::SUBROGACION) {
            $this->subrogacionService->activarPorMovimiento($movimiento);
        }
    }

    /**
     * Anular la acción arrastra lo que dependía de ella. Hoy solo la
     * subrogación: sin acto que la respalde no puede seguir vigente, ni su
     * subrogante conservar la firma.
     *
     * @param  array<string, mixed>  $datos
     */
    private function aplicarAnulacion(MovimientoPersonal $movimiento, array $datos): void
    {
        $this->aplicarDictamenSiViene($movimiento, $datos);

        if ($movimiento->tipo_movimiento === TipoMovimientoPersonal::SUBROGACION) {
            $this->subrogacionService->cancelarPorMovimiento($movimiento);
        }
    }

    /**
     * Un ingreso ya no cierra por su cuenta el vínculo vigente del servidor.
     * Talento Humano no maneja "ascenso": cuando alguien pasa a otro puesto se
     * registran dos acciones formales y separadas — primero la Cesación de
     * Funciones, después el Ingreso y Vinculación —, cada una con su propio
     * documento. Cerrar el contrato en silencio desde el ingreso convertía ese
     * acto en un efecto colateral sin acción de personal que lo respaldara.
     */
    private function assertSinVinculoVigente(MovimientoPersonal $movimiento): void
    {
        $servidor = Servidor::with('contratoVigente')->find($movimiento->servidor_id);

        if (!$servidor?->contratoVigente) {
            return;
        }

        throw new ReglaNegocioException(
            'El servidor mantiene un vínculo laboral vigente. Registre primero la '
                .'Cesación de Funciones del puesto actual y luego este Ingreso y Vinculación.'
        );
    }

    /**
     * Las cesaciones de funciones (renuncia, destitución, jubilación,
     * incapacidad, contrato finalizado) cierran el vínculo vigente al
     * registrarse. Antes de esta fase ninguna lo hacía: la acción quedaba
     * registrada pero el ContratoServidor seguía vigente.
     */
    private function cerrarVinculo(MovimientoPersonal $movimiento): void
    {
        $servidor = Servidor::with('contratoVigente')->find($movimiento->servidor_id);

        if (!$servidor?->contratoVigente) {
            throw new ReglaNegocioException(
                'El servidor no tiene un vínculo laboral vigente que cesar.'
            );
        }

        $subtipo = $movimiento->subtipoEfectivo();

        $this->contratoServidorService->cerrar($servidor->contratoVigente, [
            'motivo_fin' => $subtipo->etiqueta().' — Acción de Personal #'.$movimiento->id.'.',
            'fecha_fin'  => $movimiento->fecha_efectiva?->toDateString() ?? now()->toDateString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function aplicarDatosVinculo(MovimientoPersonal $movimiento, array $datos): void
    {
        $campos = [
            'numero_contrato', 'remuneracion_propuesta', 'partida_presupuestaria_id',
            'puede_marcar', 'resolucion_numero', 'fecha_fin_propuesta',
        ];

        foreach ($campos as $campo) {
            if (array_key_exists($campo, $datos) && $datos[$campo] !== null) {
                $movimiento->{$campo} = $datos[$campo];
            }
        }
    }

    /**
     * Exclusión mutua y completitud de los datos "propuestos" antes de
     * intentar materializar nada — para fallar con un mensaje de negocio
     * claro en vez de dejar que crear()/reestructurarDesdeMovimiento()
     * truenen con un error de tipo o de BD.
     */
    private function validarDatosPropuestos(MovimientoPersonal $movimiento): void
    {
        $tipo = $movimiento->tipo_movimiento;

        if ($tipo->creaVinculo() && $tipo->modificaVinculo()) {
            throw new \LogicException(
                "El tipo_movimiento '{$tipo->value}' no puede ser creaVinculo() y modificaVinculo() a la vez."
            );
        }

        if ($tipo->creaVinculo()) {
            // Primero lo estructural: si el servidor sigue vinculado, falta un
            // acto de personal completo (la cesación), no un dato del
            // formulario. Reportarlo antes evita que el usuario complete
            // campos para toparse después con el bloqueo de fondo.
            $this->assertSinVinculoVigente($movimiento);

            if (!$movimiento->tipo_nombramiento_propuesto) {
                throw new ReglaNegocioException(
                    'No se puede registrar el ingreso sin especificar el tipo de nombramiento propuesto.'
                );
            }

            if (!$movimiento->puesto_destino_id || !$movimiento->unidad_destino_id) {
                throw new ReglaNegocioException(
                    'No se puede registrar el ingreso sin especificar puesto y unidad administrativa propuestos.'
                );
            }

            // Obligatorios para aprobar: el vínculo se materializa con estos
            // datos, no se completan después. La remuneración se pide aquí y
            // no al crear la acción porque en Código del Trabajo y Servicios
            // Profesionales se negocia en el contrato — no se deriva del
            // puesto como en el régimen LOSEP.
            $faltantes = [];

            if (blank($movimiento->numero_contrato)) {
                $faltantes[] = 'número de contrato';
            }

            if ($movimiento->remuneracion_propuesta === null) {
                $faltantes[] = 'remuneración';
            }

            if ($faltantes !== []) {
                throw new ReglaNegocioException(
                    'No se puede registrar el ingreso sin '.implode(' ni ', $faltantes).'.'
                );
            }

            $this->validarPlazoDelReemplazo($movimiento);
        }

        // Solo los subtipos que reubican necesitan puesto destino. Una comisión
        // de servicios comparte el tipo paraguas pero no mueve a nadie de
        // puesto, así que exigírselo sería falso.
        $subtipo = $movimiento->subtipoEfectivo();

        if ($subtipo?->modificaPuesto() && !$movimiento->puesto_destino_id) {
            throw new ReglaNegocioException(
                "No se puede registrar '{$subtipo->etiqueta()}' sin especificar el puesto propuesto."
            );
        }
    }

    /**
     * Un reemplazo no puede durar más que la ausencia que cubre.
     *
     * MovimientoPersonalService ya lo comprueba al crear la acción, pero eso no
     * alcanza: el borrador es editable y el plazo también llega en la propia
     * aprobación, así que la fecha puede alargarse después de aquella
     * comprobación. Se vuelve a verificar aquí, que es el instante en que el
     * contrato nace — si no, el suplente quedaría trabajando sobre una plaza
     * cuyo titular ya regresó.
     */
    private function validarPlazoDelReemplazo(MovimientoPersonal $movimiento): void
    {
        if (! $movimiento->cubre_movimiento_id) {
            return;
        }

        $ausencia = $movimiento->cubreMovimiento;

        if (! $ausencia?->fecha_fin || ! $movimiento->fecha_fin_propuesta) {
            return;
        }

        $finAusencia = $ausencia->fecha_fin->toDateString();

        if ($movimiento->fecha_fin_propuesta->toDateString() > $finAusencia) {
            throw new ReglaNegocioException(
                "El reemplazo no puede extenderse más allá del {$finAusencia}, "
                    .'que es cuando termina la ausencia que cubre.'
            );
        }
    }

    /**
     * Al suscribirse una acción marcada con 'requiere_dictamen_medico' se abre
     * la solicitud de ficha de salud ocupacional, salvo que ya tenga una
     * enlazada — el caso del ingreso por reclutamiento, donde el dictamen es
     * previo al movimiento y SolicitudCertificacionController lo asocia al
     * confirmar la incorporación.
     */
    private function solicitarCertificacionMedicaSiHaceFalta(MovimientoPersonal $movimiento): void
    {
        if (!$movimiento->requiere_dictamen_medico) {
            return;
        }

        if ($movimiento->solicitudCertificacion()->exists()) {
            return;
        }

        $servidor = $movimiento->servidor()->with('puesto.cargo')->first();

        if (!$servidor) {
            throw new ReglaNegocioException(
                'No se puede solicitar la certificación médica: el movimiento no tiene servidor asociado.'
            );
        }

        SolicitudCertificacionMedica::create([
            'tipo_evento'            => $movimiento->tipo_movimiento->creaVinculo() ? 'ingreso' : 'retiro',
            'origen'                 => 'expediente',
            'servidor_id'            => $servidor->id,
            'movimiento_personal_id' => $movimiento->id,
            'cedula_paciente'        => $servidor->cedula,
            'nombres_paciente'       => trim("{$servidor->nombre} {$servidor->apellido}"),
            'correo_paciente'        => $servidor->correo_personal,
            'puesto_solicitado'      => $servidor->puesto?->cargo?->nombre,
            'solicitado_por'         => auth()->id(),
            'estado'                 => 'pendiente',
            'fecha_limite'           => now()->addDays(7)->toDateString(),
            'observaciones'          => 'Generada automáticamente por la acción de personal #'.$movimiento->id.'.',
        ]);
    }

    /**
     * Una acción que exige ficha de salud ocupacional no puede registrarse sin
     * dictamen de aptitud. 'no_apto' tampoco habilita el registro: es una
     * decisión que Talento Humano debe resolver anulando o corrigiendo la
     * acción, no un trámite que el sistema pueda dar por cumplido.
     */
    private function validarDictamenMedico(MovimientoPersonal $movimiento): void
    {
        if (!$movimiento->requiere_dictamen_medico) {
            return;
        }

        $solicitud = $movimiento->solicitudCertificacion()->first();

        if (!$solicitud || $solicitud->estado !== 'completada') {
            throw new ReglaNegocioException(
                'Esta acción de personal requiere ficha de salud ocupacional: '
                    .'el dispensario médico aún no ha emitido el dictamen.'
            );
        }

        if (!in_array($solicitud->dictamen, ['apto', 'apto_con_restricciones'], true)) {
            throw new ReglaNegocioException(
                'El dictamen médico no es de aptitud ('.($solicitud->dictamen ?? 'sin dictamen')
                    .'): no se puede registrar esta acción de personal.'
            );
        }
    }

    private function aplicarNotificacion(MovimientoPersonal $movimiento, array $datos): void
    {
        $movimiento->notificado_por     = $datos['notificado_por'] ?? auth()->id();
        $movimiento->fecha_notificacion = now();
    }

    /**
     * Correlativo + año (ej. AP-2026-0001). lockForUpdate() reduce la
     * ventana de carrera entre solicitudes concurrentes dentro del mismo
     * año; no sustituye una secuencia dedicada, pero es suficiente para el
     * volumen de este módulo — la columna sigue siendo unique a nivel BD.
     */
    private function generarCodigoRegistro(): string
    {
        $anio = now()->year;

        // Postgres no permite FOR UPDATE junto a un count() agregado: se
        // seleccionan las filas (bloqueándolas) y se cuentan en PHP.
        $correlativo = DB::table('movimientos_personal')
            ->where('codigo_registro', 'like', "AP-{$anio}-%")
            ->lockForUpdate()
            ->get(['id'])
            ->count();

        return sprintf('AP-%d-%04d', $anio, $correlativo + 1);
    }
}
