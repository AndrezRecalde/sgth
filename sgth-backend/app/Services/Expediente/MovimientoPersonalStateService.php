<?php

namespace App\Services\Expediente;

use App\Enums\AptitudMedica;
use App\Enums\EstadoAccionPersonal;
use App\Enums\PartidaPorModalidad;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
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
     * adelante": REGISTRADA solo se alcanza desde SUSCRITA. ANULADA es el
     * único estado terminal.
     *
     * Anular desde REGISTRADA y desde NOTIFICADA se abrió el 2026-09-29. Antes
     * solo se podía anular lo que aún no había surtido efecto, y eso dejaba a
     * Talento Humano sin ninguna salida cuando el error aparecía después: la
     * acción ya no se editaba —guarda de inmutabilidad—, ya no se anulaba, y lo
     * único que existía era `corregir()`, que emitía un SEGUNDO documento con
     * otro correlativo dejando el primero vigente. Preguntado, TH dijo que lo
     * correcto es anular y emitir uno nuevo, no enmendar.
     *
     * Anular después de registrar no es un cambio de estado a secas: hay que
     * deshacer lo que el registro hizo sobre el vínculo. De eso se ocupa
     * `revertirEfectoSobreElVinculo()`.
     */
    private const TRANSICIONES = [
        'borrador'   => ['suscrita', 'anulada'],
        'suscrita'   => ['registrada', 'anulada'],
        'registrada' => ['notificada', 'anulada'],
        'notificada' => ['anulada'],
        'anulada'    => [],
    ];

    public function transicionar(
        MovimientoPersonal $movimiento,
        EstadoAccionPersonal $destino,
        array $datos = []
    ): MovimientoPersonal {
        $origen = $movimiento->estado;

        // También en las cascadas —anular una sanción desde Disciplinario, una
        // subrogación desde su pantalla—: quien las dispara sobre sí mismo
        // sigue tramitando su propia acción.
        TramiteSobreSiMismo::impedir($movimiento->servidor_id);

        $this->assertTransicionPermitida($origen, $destino);

        return DB::transaction(function () use ($movimiento, $destino, $datos) {
            // Sin rama 'default': los cuatro estados de arriba son los únicos
            // alcanzables. El quinto, BORRADOR, no figura como destino en
            // ninguna entrada de TRANSICIONES —de un borrador se sale, nunca se
            // vuelve— y assertTransicionPermitida() ya lo rechazó. La rama
            // existía y no se ejecutaba nunca; sin ella, el match es exhaustivo
            // y añadir un estado al enum falla ruidosamente aquí en vez de caer
            // en silencio sobre el dictamen.
            match ($destino) {
                EstadoAccionPersonal::SUSCRITA   => $this->aplicarSuscrita($movimiento, $datos),
                EstadoAccionPersonal::REGISTRADA => $this->aplicarRegistro($movimiento, $datos),
                EstadoAccionPersonal::NOTIFICADA => $this->aplicarNotificacion($movimiento, $datos),
                EstadoAccionPersonal::ANULADA    => $this->aplicarAnulacion($movimiento, $datos),
            };

            $movimiento->estado = $destino;
            $movimiento->save();

            return $movimiento->fresh();
        });
    }

    /**
     * A qué estados puede llevar esta acción quien pregunta: los que el grafo
     * permite desde el actual y para los que tiene el permiso, y ninguno si la
     * acción es suya. Lo lee la pantalla, para no ofrecer un botón que
     * respondería 403 o 422.
     *
     * @return list<EstadoAccionPersonal>
     */
    public function destinosPara(MovimientoPersonal $movimiento, ?User $usuario): array
    {
        if ($usuario === null || $movimiento->estado === null || $movimiento->esSobre($usuario)) {
            return [];
        }

        $destinos = array_map(
            fn (string $estado) => EstadoAccionPersonal::from($estado),
            self::TRANSICIONES[$movimiento->estado->value] ?? [],
        );

        return array_values(array_filter(
            $destinos,
            fn (EstadoAccionPersonal $destino) => ($permiso = $destino->permisoParaLlegar()) === null
                || $usuario->can($permiso->value),
        ));
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

        // Se verifica la partida que se va a usar, en este orden: la que la
        // acción fijó —que es la que Talento Humano eligió—, luego la del
        // vínculo vigente, y solo al final la del puesto. Mirar únicamente la
        // del puesto certificaba una partida distinta de la que paga: un
        // contrato ocasional sobre un puesto de carrera se imputa a 510510 y no
        // a la 510105 con la que el orgánico presupuesta la plaza.
        $puesto = $movimiento->puestoDestino ?? $movimiento->puestoOrigen ?? $movimiento->servidor?->puesto;

        // La subrogación y el encargo son la excepción a esa cadena: lo que se
        // paga no es el sueldo del puesto sino la diferencia, y esa tiene
        // partida propia —510512 y 510513, confirmadas por la Dirección
        // Financiera—. Si la acción no la trae, es porque no están registradas
        // o están inactivas, y entonces el respaldo imputaba el gasto a la
        // partida del contrato del subrogante: exactamente lo que
        // SubrogacionService::partidaDeLaDiferencia() decía que este guard
        // rechazaría. Comprobado que no lo rechazaba: la acción se suscribía con
        // la partida de otro vínculo.
        $heredaPartida = $movimiento->tipo_movimiento !== TipoMovimientoPersonal::SUBROGACION;

        $partida = $movimiento->partidaPresupuestaria ?? ($heredaPartida
            ? ($movimiento->servidor?->contratoVigente?->partidaPresupuestaria
                ?? $puesto?->partidaPresupuestaria)
            : null);

        if (!$partida && ! $heredaPartida) {
            throw new ReglaNegocioException(
                'La partida de la diferencia no está disponible en el catálogo '
                    .'('.PartidaPorModalidad::SUBROGACION.' subrogaciones, '
                    .PartidaPorModalidad::ENCARGO.' encargos). Solicítela a la '
                    .'Dirección Financiera: la diferencia no puede imputarse a la '
                    .'partida del puesto ni a la del contrato del servidor.'
            );
        }

        if (!$partida) {
            throw new ReglaNegocioException(
                'Esta acción no tiene partida presupuestaria asignada: '
                    .'indíquela antes de suscribirla.'
            );
        }

        if (!$partida->disponible) {
            throw new ReglaNegocioException(
                "La partida {$partida->codigo} no tiene disponibilidad presupuestaria "
                    .'verificada. Solicítela a la Dirección Financiera antes de suscribir.'
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

        /*
        | Registrar no es lo mismo que surtir efecto (diseño, 6.2; fase 1.6).
        | Lo que rige hoy o antes se aplica aquí mismo; lo que rige más tarde
        | queda pendiente de vigencia y lo aplica `aplicarVigentes()`, desde el
        | comando diario, el día en que rige. Hasta aquí todo se aplicaba al
        | registrar: una cesación que regía el mes próximo cerraba hoy el
        | vínculo y sacaba hoy al servidor de la nómina.
        |
        | Lo que el efecto necesita se comprueba ahora, rija cuando rija, para
        | que el día en que se aplique no aparezca un obstáculo que nadie vio.
        */
        $this->validarQueElEfectoSePodraAplicar($movimiento);

        $hoy = now()->toDateString();

        if ($movimiento->rigeEn($hoy)) {
            // Antes, lo que ya debía regir para este servidor y el comando aún
            // no aplicó: la cesación del «ascenso» va antes que su ingreso. Si
            // algo de eso falla, este registro no sigue sobre un vínculo a medias.
            $previas = $this->aplicarVigentes($hoy, $movimiento->servidor_id, $movimiento->id);

            if ($previas['fallidas'] !== []) {
                throw new ReglaNegocioException(
                    "Antes de esta acción hay que aplicar '{$previas['fallidas'][0]['codigo']}', "
                        .'que ya rige, y no se pudo: '.$previas['fallidas'][0]['motivo']
                );
            }

            $this->aplicarEfecto($movimiento);
        }
    }

    /**
     * Aplica los efectos pendientes de lo que ya rige en `$fecha`: crea, mueve
     * o cierra los vínculos y activa las subrogaciones (fase 1.6). Lo llama
     * el comando diario `sgth:acciones:aplicar-vigentes`, y también el registro
     * de una acción que rige ya, para que nada quede atrás.
     *
     * En orden de fecha, y en un mismo día los cierres antes que los ingresos:
     * es el «ascenso» de Talento Humano —cesación y nuevo ingreso el mismo
     * día—, y el ingreso no puede nacer con el vínculo anterior abierto.
     *
     * Cada acción va en su propia transacción: si una falla, queda pendiente
     * con su motivo y las demás siguen.
     *
     * @return array{aplicadas: list<array{id: int, codigo: string, servidor_id: int}>, fallidas: list<array{id: int, codigo: string, servidor_id: int, motivo: string}>}
     */
    public function aplicarVigentes(string $fecha, ?int $servidorId = null, ?int $excepto = null): array
    {
        $pendientes = MovimientoPersonal::pendientesDeVigencia()
            ->whereDate('fecha_efectiva', '<=', $fecha)
            ->when($servidorId, fn ($q) => $q->where('servidor_id', $servidorId))
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))
            ->orderBy('fecha_efectiva')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (MovimientoPersonal $m) => [
                $m->fecha_efectiva->toDateString(),
                match (true) {
                    $m->cierraElVinculo()                  => 0,
                    $m->reubicaAlServidor()                => 1,
                    $m->tipo_movimiento->creaVinculo()     => 3,
                    default                                => 2,
                },
                $m->id,
            ])
            ->values();

        $resultado = ['aplicadas' => [], 'fallidas' => []];

        foreach ($pendientes as $movimiento) {
            $fila = [
                'id'          => $movimiento->id,
                'codigo'      => (string) $movimiento->codigo_registro,
                'servidor_id' => $movimiento->servidor_id,
            ];

            try {
                DB::transaction(function () use ($movimiento) {
                    $this->aplicarEfecto($movimiento);
                    $movimiento->save();
                });

                $resultado['aplicadas'][] = $fila;
            } catch (ReglaNegocioException $e) {
                $resultado['fallidas'][] = [...$fila, 'motivo' => $e->getMessage()];
            }
        }

        return $resultado;
    }

    /**
     * Lo que el registro de una acción le hace al vínculo, una sola vez.
     */
    private function aplicarEfecto(MovimientoPersonal $movimiento): void
    {
        if ($movimiento->efecto_aplicado_en !== null) {
            return;
        }

        $tipo = $movimiento->tipo_movimiento;

        if ($tipo->creaVinculo()) {
            // Al registrar se admitió un vínculo abierto si su cesación ya
            // estaba registrada para antes; el día en que rige el ingreso, esa
            // cesación tiene que haberse aplicado (`aplicarVigentes()` va por
            // fecha y con los cierres primero). Si no, no se crea un segundo
            // vínculo encima del primero.
            if (Servidor::with('contratoVigente')->find($movimiento->servidor_id)?->contratoVigente) {
                throw new ReglaNegocioException(
                    'El servidor todavía tiene un vínculo vigente: aplique o revise primero la '
                        .'Cesación de Funciones del puesto actual.'
                );
            }

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
                // La partida viaja de la acción al contrato: es la que Talento
                // Humano eligió al aprobar, y la que va a pagar este vínculo.
                // Si la acción no se pronunció, cae a la del puesto.
                'partida_presupuestaria_id' => $movimiento->partida_presupuestaria_id
                    ?? $movimiento->puestoDestino?->partida_presupuestaria_id,
                'resolucion_numero'        => $movimiento->resolucion_numero,
                // null = la acción no se pronunció: el contrato conserva su
                // propio default en vez de forzarle un false.
                ...($movimiento->puede_marcar === null
                    ? []
                    : ['puede_marcar' => $movimiento->puede_marcar]),
                'estado'                   => 'vigente',
            ], $movimiento);
        } elseif ($movimiento->reubicaAlServidor()) {
            // El traspaso y la prestación de servicios reubican dentro del
            // mismo vínculo. Los otros tres subtipos del tipo paraguas no entran
            // aquí: las comisiones son ausencias temporales y el servidor
            // conserva su puesto, y el traslado es entre instituciones —ver
            // modificaPuesto()—, así que no hay puesto de destino dentro del GAD
            // al que moverlo.
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

        $movimiento->efecto_aplicado_en = now();
    }

    /**
     * Lo que el efecto va a necesitar el día en que rija, comprobado al
     * registrar. El ingreso ya lo valida `validarDatosPropuestos()`; aquí, la
     * plaza del ingreso que espera su fecha, y el vínculo que una cesación o un
     * traslado van a tocar.
     */
    private function validarQueElEfectoSePodraAplicar(MovimientoPersonal $movimiento): void
    {
        $hoy = now()->toDateString();

        if ($movimiento->tipo_movimiento->creaVinculo()) {
            // Si rige ya, `crear()` lo comprueba al materializarlo.
            if (! $movimiento->rigeEn($hoy) && $movimiento->puesto_destino_id && $movimiento->tipo_nombramiento_propuesto) {
                $this->contratoServidorService->validarVacante(
                    (int) $movimiento->puesto_destino_id,
                    $movimiento->tipo_nombramiento_propuesto->value,
                    null,
                    $movimiento->cubre_movimiento_id ? (int) $movimiento->cubre_movimiento_id : null,
                    $movimiento->id,
                );
            }

            return;
        }

        if (($movimiento->cierraElVinculo() || $movimiento->reubicaAlServidor())
            && ! Servidor::with('contratoVigente')->find($movimiento->servidor_id)?->contratoVigente
        ) {
            throw new ReglaNegocioException(
                $movimiento->cierraElVinculo()
                    ? 'El servidor no tiene un vínculo laboral vigente que cesar.'
                    : 'El servidor no tiene un vínculo vigente para reubicar.'
            );
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

        if (! blank($datos['motivo_anulacion'] ?? null)) {
            $movimiento->motivo_anulacion = trim($datos['motivo_anulacion']);
        }

        if ($movimiento->tipo_movimiento === TipoMovimientoPersonal::SUBROGACION) {
            $this->subrogacionService->cancelarPorMovimiento($movimiento);
        }

        // Y si ya estaba registrada, deshacer lo que el registro hizo sobre el
        // vínculo: anular un acto que ya surtió efecto no es solo cambiarle el
        // estado a la fila.
        $this->revertirEfectoSobreElVinculo($movimiento);
    }

    /**
     * Deshace sobre el vínculo lo que hizo `aplicarRegistro()`.
     *
     * Solo corre si la acción llegó a registrarse —el correlativo es la prueba
     * de que pasó por ahí—; anular un borrador o algo suscrito no tiene nada
     * que revertir, que es como funcionaba hasta ahora.
     *
     * Es el espejo exacto de `aplicarRegistro()`, rama por rama, y pregunta lo
     * mismo que aquél a través de `MovimientoPersonal::tocaElVinculo()`. Si se
     * añade un tipo que toque el vínculo al registrarse, hay que añadirlo
     * también aquí, o su anulación dejará el contrato diciendo algo que ningún
     * acto respalda.
     */
    private function revertirEfectoSobreElVinculo(MovimientoPersonal $movimiento): void
    {
        if (blank($movimiento->codigo_registro) || ! $movimiento->tocaElVinculo()) {
            return;
        }

        // También para la que aún espera su fecha: otra posterior —el ingreso
        // que sigue a una cesación— puede haberla dado por supuesta.
        $this->assertEsLaUltimaQueTocaElVinculo($movimiento);

        // Pendiente de vigencia: no le hizo nada al vínculo todavía, así que no
        // hay nada que deshacer (fase 1.6).
        if ($movimiento->efecto_aplicado_en === null) {
            return;
        }

        if ($movimiento->tipo_movimiento->creaVinculo()) {
            $this->deshacerVinculoCreado($movimiento);
            return;
        }

        if ($movimiento->reubicaAlServidor()) {
            $this->devolverAlPuestoDeOrigen($movimiento);
            return;
        }

        $this->deshacerCierreDeVinculo($movimiento);
    }

    /**
     * Anular el ingreso que creó un vínculo borra ese vínculo.
     *
     * No lo «cierra»: cerrarlo dejaría en el expediente un contrato terminado
     * que afirma que la persona estuvo vinculada y dejó de estarlo, y eso no
     * ocurrió — el acto que lo creó quedó sin efecto, así que el vínculo nunca
     * debió existir. El borrado es lógico (SoftDeletes), de modo que la fila
     * sigue ahí para auditoría junto al registro de la anulación.
     *
     * Un ingreso nunca cierra el vínculo anterior de nadie
     * (`assertSinVinculoVigente`), así que aquí no hay nada que reabrir.
     */
    private function deshacerVinculoCreado(MovimientoPersonal $movimiento): void
    {
        $contrato = ContratoServidor::where('movimiento_origen_id', $movimiento->id)->first();

        if (! $contrato) {
            throw new ReglaNegocioException(
                'No se encuentra el vínculo que creó esta acción, así que anularla dejaría '
                    .'el expediente a medias. Revíselo con la Dirección de Talento Humano.'
            );
        }

        $servidorId = $contrato->servidor_id;
        $contrato->delete();

        $this->contratoServidorService->sincronizarPuestoDesdeVinculo($servidorId);
    }

    /**
     * Anular una cesación devuelve a la vida el vínculo que cerró.
     */
    private function deshacerCierreDeVinculo(MovimientoPersonal $movimiento): void
    {
        $contrato = ContratoServidor::where('movimiento_cierre_id', $movimiento->id)->first();

        if (! $contrato) {
            throw new ReglaNegocioException(
                'No se encuentra el vínculo que cerró esta acción, así que anularla no '
                    .'devolvería al servidor a su situación anterior. Revíselo con la '
                    .'Dirección de Talento Humano.'
            );
        }

        $this->contratoServidorService->reabrirPorAnulacion($contrato);
    }

    /**
     * Anular un traspaso o una prestación de servicios devuelve al servidor al
     * puesto del que venía.
     *
     * El origen no se deduce: está congelado en la propia acción desde que se
     * creó (`MovimientoPersonalService::capturarSituacionActual()`), y es la
     * columna «situación actual» del documento impreso.
     */
    private function devolverAlPuestoDeOrigen(MovimientoPersonal $movimiento): void
    {
        $contrato = Servidor::with('contratoVigente')
            ->find($movimiento->servidor_id)?->contratoVigente;

        if (! $contrato || ! $movimiento->puesto_origen_id) {
            throw new ReglaNegocioException(
                'No se puede devolver al servidor a su puesto anterior: falta el vínculo '
                    .'vigente o la situación de origen de esta acción.'
            );
        }

        $contrato->update([
            'puesto_id'                => $movimiento->puesto_origen_id,
            'unidad_administrativa_id' => $movimiento->unidad_origen_id
                ?? $contrato->unidad_administrativa_id,
        ]);

        $this->contratoServidorService->sincronizarPuestoDesdeVinculo($movimiento->servidor_id);
    }

    /**
     * No se anula una acción que otra posterior ya dio por supuesta.
     *
     * Revertir mira el estado de HOY del vínculo, no el de entonces: si al
     * traspaso de marzo le siguió otro en julio, deshacer el de marzo
     * devolvería al servidor al puesto de enero y borraría de hecho el de
     * julio, que sigue registrado y con su documento firmado. Igual con una
     * cesación seguida de un ingreso nuevo.
     *
     * Se anula de la última hacia atrás. Quien quiera deshacer la de marzo
     * tiene que anular antes la de julio: es más trabajo y es lo correcto,
     * porque cada acto que se deshace deja su propia constancia.
     */
    private function assertEsLaUltimaQueTocaElVinculo(MovimientoPersonal $movimiento): void
    {
        $ultima = MovimientoPersonal::where('servidor_id', $movimiento->servidor_id)
            ->whereNotNull('codigo_registro')
            ->whereIn('estado', [
                EstadoAccionPersonal::REGISTRADA->value,
                EstadoAccionPersonal::NOTIFICADA->value,
            ])
            // `fecha_registro` es una fecha sin hora, así que dos del mismo día
            // empatan; el id desempata por orden de creación.
            ->orderByDesc('fecha_registro')
            ->orderByDesc('id')
            ->get()
            ->first(fn (MovimientoPersonal $otro) => $otro->tocaElVinculo());

        if ($ultima && $ultima->id !== $movimiento->id) {
            throw new ReglaNegocioException(
                'No se puede anular esta acción: después de ella se registró '
                    ."'{$ultima->codigo_registro}', que también afecta al vínculo del "
                    .'servidor. Anule primero la más reciente.'
            );
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

        // El «ascenso»: la cesación del puesto actual ya está registrada y rige
        // a más tardar el día en que rige este ingreso. El vínculo se cierra
        // antes de que este nazca (`aplicarVigentes()` aplica los cierres
        // primero), así que no hay dos vínculos a la vez.
        $cesacionPendiente = MovimientoPersonal::pendientesDeVigencia()
            ->where('servidor_id', $movimiento->servidor_id)
            ->whereDate('fecha_efectiva', '<=', $movimiento->fecha_efectiva)
            ->get()
            ->contains(fn (MovimientoPersonal $m) => $m->cierraElVinculo());

        if ($cesacionPendiente) {
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
            // Qué acto lo cerró, para poder reabrirlo si se anula. El texto de
            // `motivo_fin` ya lo decía, pero un texto no es algo contra lo que
            // consultar.
            'movimiento_cierre_id' => $movimiento->id,
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

        // Solo lo que reubica necesita puesto destino. Una comisión de
        // servicios comparte el tipo paraguas pero no mueve a nadie de puesto,
        // así que exigírselo sería falso.
        //
        // La etiqueta sale del subtipo cuando lo hay y del tipo cuando no: la
        // prestación de servicios reubica sin tener subtipo, y el mensaje decía
        // el nombre de otra cosa.
        if ($movimiento->reubicaAlServidor() && !$movimiento->puesto_destino_id) {
            $etiqueta = $movimiento->subtipoEfectivo()?->etiqueta()
                ?? $movimiento->tipo_movimiento->etiqueta();

            throw new ReglaNegocioException(
                "No se puede registrar '{$etiqueta}' sin especificar el puesto propuesto."
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

        if (!AptitudMedica::tryFrom((string) $solicitud->dictamen)?->habilitaIncorporacion()) {
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

        // Se toma el último código del año, no cuántos hay: contar filas
        // supone que la secuencia no tiene huecos, y basta uno para que el
        // correlativo apunte a un código ya emitido. Entonces el INSERT choca
        // contra movimientos_personal_codigo_registro_unique, la transición
        // completa se deshace —corre dentro de DB::transaction— y el conteo
        // se queda donde estaba: el siguiente intento repite el mismo código
        // duplicado y Talento Humano no vuelve a registrar una acción en todo
        // el año. El usuario, además, veía «verifique que no esté duplicando
        // datos que deben ser únicos (ej. cédula)» e iba a revisar la cédula.
        //
        // Postgres no permite FOR UPDATE junto a un agregado, así que se
        // ordena y se toma la primera fila, bloqueándola.
        $ultimo = DB::table('movimientos_personal')
            ->where('codigo_registro', 'like', "AP-{$anio}-%")
            ->orderByDesc('codigo_registro')
            ->lockForUpdate()
            ->value('codigo_registro');

        // El sufijo va relleno a cuatro cifras, así que el mayor
        // lexicográfico es también el mayor numérico.
        $correlativo = $ultimo ? (int) substr($ultimo, -4) : 0;

        return sprintf('AP-%d-%04d', $anio, $correlativo + 1);
    }
}
