<?php

namespace App\Services\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\ClaseAccionPersonal;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\InstrumentoActo;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\SupuestoProvisional;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoParentesco;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MovimientoPersonalService
{
    /**
     * Registra una acción elegida por su clase legal, que es como la pide el
     * formulario de «Nueva acción de personal».
     *
     * Valida lo que es de la clase —que se cree desde el formulario, que traiga
     * su causal, que aplique al nombramiento vigente— con el nombre que ve
     * Talento Humano, y la traduce al tipo/subtipo con el que se guarda. El
     * resto lo hace `registrar()`, igual que para los módulos que crean por
     * tipo (Disciplinario, reclutamiento, contratos vencidos).
     */
    public function registrarPorClase(
        int $servidorId,
        ClaseAccionPersonal $clase,
        ?string $causal,
        array $datos
    ): MovimientoPersonal {
        if (! $clase->seCreaDesdeElFormulario()) {
            throw new ReglaNegocioException(
                "\"{$clase->etiqueta()}\" se registra desde su propia pantalla, no desde aquí."
            );
        }

        $causalElegida = $this->resolverCausal($clase, $causal);

        $servidor = Servidor::with('contratoVigente')->findOrFail($servidorId);
        $nombramiento = $servidor->contratoVigente?->tipo_nombramiento;

        if ($clase->requiereVinculo()) {
            if (! $nombramiento instanceof TipoNombramiento) {
                throw new ReglaNegocioException(
                    'El servidor no tiene un contrato vigente con tipo de nombramiento definido.'
                );
            }

            if (! $clase->elegiblePara($nombramiento, $causalElegida)) {
                $etiqueta = $causalElegida
                    ? "{$clase->etiqueta()} por {$causalElegida->etiqueta()}"
                    : $clase->etiqueta();

                throw new ReglaNegocioException(
                    "\"{$etiqueta}\" no aplica para el tipo de nombramiento vigente del servidor ({$nombramiento->etiqueta()})."
                );
            }
        }

        [$tipo, $subtipo] = $clase->tipoYSubtipo($nombramiento, $causalElegida);

        return $this->registrar($servidorId, [
            ...$datos,
            'tipo_movimiento'    => $tipo->value,
            'subtipo_movimiento' => $subtipo?->value,
            'clase'              => $clase->value,
        ]);
    }

    /**
     * La causal es obligatoria donde la clase la tiene, y no se admite donde no.
     */
    private function resolverCausal(ClaseAccionPersonal $clase, ?string $causal): ?SubtipoMovimientoPersonal
    {
        if (! $clase->requiereCausal()) {
            if ($causal !== null) {
                throw new ReglaNegocioException(
                    "\"{$clase->etiqueta()}\" no lleva causal."
                );
            }

            return null;
        }

        $elegida = $causal !== null ? SubtipoMovimientoPersonal::tryFrom($causal) : null;

        // La destitución y el visto bueno existen, pero no se registran a mano:
        // nacen en Disciplinario con su sumario o la resolución del Inspector.
        if ($elegida && ! $elegida->seRegistraDesdeElFormulario()) {
            throw new ReglaNegocioException(
                "\"{$elegida->etiqueta()}\" se registra desde Disciplinario, con su procedimiento, "
                    .'y no desde aquí.'
            );
        }

        if (! $elegida || ! in_array($elegida, $clase->causales(), true)) {
            $validas = implode(', ', array_map(
                fn (SubtipoMovimientoPersonal $c) => $c->etiqueta(),
                $clase->causales()
            ));

            throw new ReglaNegocioException(
                "Indique la causal de la {$clase->etiqueta()}. Válidas: {$validas}."
            );
        }

        return $elegida;
    }

    /**
     * A una ocasional embarazada o en lactancia no se le termina el contrato
     * unilateralmente (diseño, 4.3; TH 11). Se comprueba al crear la acción y
     * otra vez al registrarla, por si el dato apareció entre medias.
     */
    public static function impedirTerminacionDeProtegida(int $servidorId, ?SubtipoMovimientoPersonal $causal = null): void
    {
        if (! ProteccionMaternidad::consta($servidorId)) {
            return;
        }

        // El despido de la obrera embarazada o en lactancia es ineficaz por
        // ley (Código del Trabajo, Art. 195.1; fase 4.5).
        throw new ReglaNegocioException(
            $causal === SubtipoMovimientoPersonal::DESPIDO_INTEMPESTIVO
                ? 'Consta en su ficha de salud ocupacional que está embarazada o en período de lactancia: su '
                    .'despido intempestivo es ineficaz (Código del Trabajo, Art. 195.1) y no procede.'
                : ProteccionMaternidad::aviso().' La terminación unilateral no procede.'
        );
    }

    public function registrar(int $servidorId, array $datos): MovimientoPersonal
    {
        // Aquí y no en el controlador: por este método crean sus borradores
        // también Disciplinario y Reclutamiento.
        TramiteSobreSiMismo::impedir($servidorId);

        $servidor = Servidor::with('contratoVigente.puesto')->findOrFail($servidorId);
        $tipoMovimiento = TipoMovimientoPersonal::from($datos['tipo_movimiento']);

        $subtipo = $this->resolverSubtipo($tipoMovimiento, $datos);

        // La restitución se completa sola: deja sin efecto la cesación que
        // cerró su último vínculo y vuelve al puesto que tenía (fase 2.6). Por
        // el contenedor y no por el constructor, porque ella crea por aquí la
        // remoción del provisional.
        $provisional = null;
        if ($tipoMovimiento === TipoMovimientoPersonal::RESTITUCION) {
            [$datos, $provisional] = app(RestitucionService::class)->completar($servidor, $datos);
        }

        // La autoridad electa empieza su período con la credencial del CNE, sin
        // acción de personal; al ingreso no se le registra (diseño, 9.14; fase
        // 4.1).
        if ($tipoMovimiento === TipoMovimientoPersonal::INICIO_PERIODO) {
            $datos = app(AutoridadElectaService::class)->completarInicio($servidor, $datos);
        }
        self::impedirIngresoDeAutoridad($tipoMovimiento, $datos['tipo_nombramiento_propuesto'] ?? null);

        if ($tipoMovimiento->esAccionDePersonal()) {
            $this->validarElegibilidad($servidor, $tipoMovimiento, $subtipo);
        }

        // La licencia del Consejo: con o sin remuneración y hasta 60 días al
        // año, salvo prórroga (COOTAD Art. 47 s; fase 4.1b).
        if ($tipoMovimiento === TipoMovimientoPersonal::LICENCIA_CONSEJO) {
            app(AutoridadElectaService::class)->validarLicenciaDelConsejo($servidor, $datos);
        }

        // El cese de una autoridad, con su documento de origen.
        if ($subtipo?->cierraVinculo() && $servidor->contratoVigente?->tipo_nombramiento === TipoNombramiento::ELECCION_POPULAR) {
            app(AutoridadElectaService::class)->exigirDocumentoDelCese($subtipo, $datos['resolucion_numero'] ?? null);
        }

        // El traslado: igual remuneración, puesto de la unidad y el informe de
        // la UATH (LOSEP Art. 35; fase 3.1).
        if (TrasladoService::aplica($tipoMovimiento, $subtipo, $servidor->contratoVigente?->tipo_nombramiento)) {
            $traslados = app(TrasladoService::class);
            $traslados->exigirInforme($datos['informe_uath_numero'] ?? null);
            $traslados->validarDestino(
                $servidor->contratoVigente,
                isset($datos['puesto_destino_id']) ? (int) $datos['puesto_destino_id'] : null,
                isset($datos['unidad_destino_id']) ? (int) $datos['unidad_destino_id'] : null,
            );
        }

        // El ascenso: al puesto ganado, de escala superior y antes de los 65
        // años; el nombramiento de prueba y la remuneración los fija la regla
        // (LOSEP Arts. 17 b.5, 68 y 81; fase 3.3).
        if ($tipoMovimiento->asciende()) {
            $datos = app(AscensoService::class)->completar($servidor, $datos);
        }

        // La adenda del contrato civil: lo que cambia, y nada más (fase 4.2).
        if ($tipoMovimiento->esAdenda()) {
            $datos = app(ContratoCivilService::class)->completarAdenda($servidor, $datos);
        }

        // Y lo que cambia del contrato de trabajo de un obrero (fase 4.3).
        if ($tipoMovimiento->cambiaElContratoDeTrabajo()) {
            $datos = app(ContratoDeTrabajoService::class)->completar($servidor, $tipoMovimiento, $datos);
        }

        if ($subtipo?->seImpideAProtegida()) {
            self::impedirTerminacionDeProtegida($servidorId, $subtipo);
        }

        // La terminación del obrero: la fecha de la renuncia en el desahucio, y
        // el período de prueba que no pasa de 90 días (fase 4.5).
        $datos = app(TerminacionObreroService::class)->completar($servidor, $subtipo, $datos);
        if ($subtipo === SubtipoMovimientoPersonal::PRUEBA_NO_SUPERADA_CT) {
            app(TerminacionObreroService::class)->validarPeriodoDePrueba($servidor->contratoVigente, $datos['fecha_efectiva'] ?? null);
        }

        // Las causales del provisional dependen de su supuesto (fase 3.2).
        $this->validarCausalSegunSupuesto($servidor, $subtipo);

        // El ingreso de un provisional dice para qué se da (LOSEP 17 b), y el
        // de prueba, el acta del concurso.
        if ($tipoMovimiento === TipoMovimientoPersonal::INGRESO) {
            $datos = $this->conSupuestoDelIngreso($datos);
        }

        if ($subtipo?->esComisionDeServicios()) {
            $this->validarComisionDeServicios($servidor, $subtipo, $datos);
        }

        // A qué entidad va: la comisión es servir en otra, y el intercambio es
        // entre instituciones (fase 2.3).
        if ($subtipo && self::vaAOtraInstitucion($subtipo) && blank($datos['institucion_destino'] ?? null)) {
            throw new ReglaNegocioException(
                "Indique la institución de destino de la {$subtipo->etiqueta()}."
            );
        }

        // La obligación de devengar al volver es solo de la comisión con
        // remuneración (LOSEP 30); en cualquier otra acción no significa nada.
        if ($subtipo !== SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION) {
            $datos['para_estudios_o_eventos'] = false;
        }

        if ($subtipo?->esLicenciaSinRemuneracion()) {
            $this->validarLicenciaSinRemuneracion($servidor, $subtipo, $datos);
        }

        if (!empty($datos['movimiento_previo_id'])) {
            $this->validarMovimientoPrevio($servidorId, (int) $datos['movimiento_previo_id']);
        }

        if (!empty($datos['cubre_movimiento_id'])) {
            $this->validarReemplazo($servidorId, $tipoMovimiento, $datos);
        }

        $datos['servidor_id']    = $servidorId;
        $datos['autorizado_por'] = auth()->id();

        $this->capturarSituacionActual($servidor, $datos);

        // El subtipo se persiste normalizado: si vino un tipo plano legado
        // (traslado, traspaso, comision_*, destitucion) se guarda igualmente su
        // subtipo equivalente, para que las consultas por subtipo no tengan
        // que conocer el legado. El tipo se deja intacto — el histórico no se
        // reescribe.
        if ($subtipo && $tipoMovimiento->requiereSubtipo()) {
            $datos['subtipo_movimiento'] = $subtipo->value;
        }

        // El ingreso de un ocasional es el registro de su contrato, sin acción
        // [TH N7; fase 2.7], lo de Servicios Profesionales, del contrato
        // civil (fase 4.2), y lo de un obrero, del Código del Trabajo (fase
        // 4.3). Se decide aquí, por donde entran también el
        // reemplazo y Reclutamiento, y antes de la ficha médica, que depende
        // de él.
        $datos['instrumento'] = $this->instrumentoPara(
            $tipoMovimiento, $datos['tipo_nombramiento_propuesto'] ?? null, $servidor->contratoVigente?->tipo_nombramiento,
        );

        // El término para presentarse, solo del ingreso por acción de personal:
        // 3 días, o hasta 5 con la razón (Reglamento a la LOSEP, Art. 20; fase 6.4).
        $datos = [...$datos, ...PosesionService::diasDelBorrador(
            $tipoMovimiento,
            InstrumentoActo::from($datos['instrumento']),
            $datos['dias_para_presentarse'] ?? null,
            $datos['razon_dias_adicionales'] ?? null,
        )];

        // Default de dictamen médico por tipo/subtipo. Si el llamador lo trae
        // explícito (Talento Humano lo editó, o el ingreso de reclutamiento que
        // ya tiene su dictamen en mano), se respeta.
        $datos['requiere_dictamen_medico'] = array_key_exists('requiere_dictamen_medico', $datos)
            ? (bool) $datos['requiere_dictamen_medico']
            : $this->requiereDictamenPorDefecto($tipoMovimiento, $subtipo, InstrumentoActo::from($datos['instrumento']));

        $datos['puede_marcar'] = $this->resolverPuedeMarcar($datos);

        // Todo lo que entra por aquí es un acto: nace en BORRADOR y pasa por
        // MovimientoPersonalStateService::transicionar() antes de considerarse
        // registrado. Hasta la fase 1.2 los movimientos históricos genéricos
        // —cambio de régimen, novedad de contrato— se saltaban esto y nacían
        // con el default de BD (REGISTRADA); eran bitácora, y hoy viven en
        // `eventos_vinculo`.
        $datos['estado']    = EstadoAccionPersonal::BORRADOR;
        $datos['categoria'] = CategoriaEventoVinculo::ACCION_DE_PERSONAL;

        // La restitución y la remoción del provisional que la hace caber nacen
        // juntas o no nace ninguna.
        $movimiento = $tipoMovimiento === TipoMovimientoPersonal::RESTITUCION
            ? DB::transaction(function () use ($datos, $provisional) {
                $restitucion = MovimientoPersonal::create($datos);
                app(RestitucionService::class)->prepararRemocionDelProvisional($restitucion, $provisional);

                return $restitucion;
            })
            : MovimientoPersonal::create($datos);

        // fresh(), no load(): los defaults de BD que $datos no trae no se
        // reflejarían en la respuesta que consume el controller/resource.
        return $movimiento->fresh([
            'unidadOrigen', 'unidadDestino', 'puestoOrigen.cargo', 'puestoDestino.cargo', 'autorizadoPor',
        ]);
    }

    /**
     * Edita una acción de personal que todavía está en borrador. Fuera de ese
     * estado el registro es inmutable: una vez suscrita ya circuló como
     * documento, y una vez registrada solo admite corrección por un registro
     * nuevo (MovimientoPersonalStateService::corregir()).
     */
    public function actualizarBorrador(MovimientoPersonal $movimiento, array $datos): MovimientoPersonal
    {
        TramiteSobreSiMismo::impedir($movimiento->servidor_id);

        if ($movimiento->estado !== EstadoAccionPersonal::BORRADOR) {
            throw new ReglaNegocioException(
                "Solo se puede editar una acción de personal en borrador. "
                    ."Esta está en '{$movimiento->estado->etiqueta()}'."
            );
        }

        // La acción de una subrogación es el reflejo de una fila en
        // `subrogaciones`, y este método solo escribe el movimiento. Editar aquí
        // las fechas o el puesto dejaba a los dos registros diciendo cosas
        // distintas: uno manda el documento impreso y el otro —vía
        // FirmanteOrganigramaService::subroganteDe(), que lee `subrogaciones`—
        // decide quién puede firmar.
        //
        // La regla existía desde el 2026-09-26, pero solo en la pantalla
        // (`AccionBotonEditar` esconde el botón y el modal no ofrece el tipo).
        // La ruta pide únicamente `role:admin-uath`, así que se alcanzaba sin
        // pasar por ninguna pantalla. Se corrige cancelando la subrogación y
        // registrándola de nuevo, que es lo que ya dice la interfaz.
        if ($movimiento->tipo_movimiento === TipoMovimientoPersonal::SUBROGACION) {
            throw new ReglaNegocioException(
                'Una subrogación o encargo no se edita: sus datos viven también en '
                    .'el registro de subrogaciones, que es el que decide quién firma. '
                    .'Cancélela y regístrela de nuevo.'
            );
        }

        // La marcación se vuelve a resolver siempre que la edición toque el
        // nombramiento o la casilla, y se evalúa sobre el resultado de la
        // edición —no sobre lo que vino—, porque es parcial y puede traer uno
        // sin el otro.
        //
        // Antes solo se recalculaba cuando 'puede_marcar' NO venía en la
        // petición, y el formulario lo manda siempre: la rama no corría nunca y
        // la restricción dura de admiteMarcacion() —servicios profesionales,
        // libre nombramiento y elección popular no marcan jamás— quedaba solo
        // en el camino de creación. Editando un borrador se colaba
        // `puede_marcar = true` en un contrato civil: el contrato se salvaba
        // porque ContratoServidorService vuelve a forzarlo, pero el movimiento
        // guardaba true y el documento impreso decía «Marca asistencia: Sí»
        // contra un vínculo que marcaba No. La promesa del enum es que esto no
        // depende de que la pantalla se comporte bien.
        if (array_key_exists('tipo_nombramiento_propuesto', $datos)
            || array_key_exists('puede_marcar', $datos)
        ) {
            $datos['puede_marcar'] = $this->resolverPuedeMarcar([
                'tipo_nombramiento_propuesto' => $datos['tipo_nombramiento_propuesto']
                    ?? $movimiento->tipo_nombramiento_propuesto,
                // Solo se pasa si vino: su ausencia es lo que pide el default
                // del nombramiento, y un null explícito significa «esta acción
                // no opina», que resolverPuedeMarcar() ya distingue.
                ...(array_key_exists('puede_marcar', $datos)
                    ? ['puede_marcar' => $datos['puede_marcar']]
                    : []),
            ]);
        }

        $this->revalidarPeriodoDeComision($movimiento, $datos);
        $this->revalidarPeriodoDeLicencia($movimiento, $datos);

        // Cambiar el nombramiento de un ingreso en borrador puede cambiar su
        // instrumento: de acción a registro del contrato, o al revés.
        if (array_key_exists('tipo_nombramiento_propuesto', $datos)) {
            self::impedirIngresoDeAutoridad($movimiento->tipo_movimiento, $datos['tipo_nombramiento_propuesto']);

            $datos['instrumento'] = $this->instrumentoPara(
                $movimiento->tipo_movimiento,
                $datos['tipo_nombramiento_propuesto'] ?? $movimiento->tipo_nombramiento_propuesto,
                Servidor::with('contratoVigente')->find($movimiento->servidor_id)?->contratoVigente?->tipo_nombramiento,
            );
        }

        // El término para presentarse, con lo corregido: el instrumento pudo
        // cambiar, y con él si hay término (fase 6.4).
        if (array_intersect(array_keys($datos), ['dias_para_presentarse', 'razon_dias_adicionales', 'instrumento']) !== []) {
            $datos = [...$datos, ...PosesionService::diasDelBorrador(
                $movimiento->tipo_movimiento,
                isset($datos['instrumento']) ? InstrumentoActo::from($datos['instrumento']) : $movimiento->instrumento,
                array_key_exists('dias_para_presentarse', $datos) ? $datos['dias_para_presentarse'] : $movimiento->dias_para_presentarse,
                array_key_exists('razon_dias_adicionales', $datos) ? $datos['razon_dias_adicionales'] : $movimiento->razon_dias_adicionales,
            )];
        }

        $subtipoActual = $movimiento->subtipoEfectivo();
        if ($subtipoActual && self::vaAOtraInstitucion($subtipoActual)
            && array_key_exists('institucion_destino', $datos) && blank($datos['institucion_destino'])
        ) {
            throw new ReglaNegocioException(
                "Indique la institución de destino de la {$subtipoActual->etiqueta()}."
            );
        }
        $this->revalidarReemplazo($movimiento, $datos);
        $this->revalidarTraslado($movimiento, $datos);

        if ($movimiento->tipo_movimiento === TipoMovimientoPersonal::INGRESO && array_intersect(
            array_keys($datos),
            ['tipo_nombramiento_propuesto', 'supuesto_provisional_propuesto', 'puesto_destino_id', 'acta_concurso_numero', 'acta_concurso_fecha'],
        ) !== []) {
            $datos = $this->conSupuestoDelIngreso([
                'tipo_nombramiento_propuesto'    => $movimiento->tipo_nombramiento_propuesto?->value,
                'supuesto_provisional_propuesto' => $movimiento->supuesto_provisional_propuesto?->value,
                'puesto_destino_id'              => $movimiento->puesto_destino_id,
                'acta_concurso_numero'           => $movimiento->acta_concurso_numero,
                'acta_concurso_fecha'            => $movimiento->acta_concurso_fecha?->toDateString(),
                ...$datos,
            ]);
        }

        if ($movimiento->tipo_movimiento === TipoMovimientoPersonal::RESTITUCION) {
            $datos = $this->revalidarRestitucion($movimiento, $datos);
        }

        // La licencia del Consejo, con lo corregido (fase 4.1b).
        if ($movimiento->tipo_movimiento === TipoMovimientoPersonal::LICENCIA_CONSEJO) {
            app(AutoridadElectaService::class)->validarLicenciaDelConsejo(
                Servidor::findOrFail($movimiento->servidor_id),
                [
                    'fecha_inicio'     => $movimiento->fecha_inicio?->toDateString(),
                    'fecha_fin'        => $movimiento->fecha_fin?->toDateString(),
                    'con_remuneracion' => $movimiento->con_remuneracion,
                    'prorroga_causa'   => $movimiento->prorroga_causa?->value,
                    ...$datos,
                ],
                $movimiento->id,
            );
        }

        // El inicio del período, con lo corregido (fase 4.1).
        if ($movimiento->tipo_movimiento === TipoMovimientoPersonal::INICIO_PERIODO) {
            $datos = app(AutoridadElectaService::class)->completarInicio(
                Servidor::findOrFail($movimiento->servidor_id),
                [
                    'credencial_cne_numero' => $movimiento->credencial_cne_numero,
                    'credencial_cne_fecha'  => $movimiento->credencial_cne_fecha?->toDateString(),
                    'fecha_efectiva'        => $movimiento->fecha_efectiva?->toDateString(),
                    'fecha_fin_propuesta'   => $movimiento->fecha_fin_propuesta?->toDateString(),
                    'puesto_destino_id'     => $movimiento->puesto_destino_id,
                    ...$datos,
                ],
            );
        }

        // La terminación del obrero, con lo corregido (fase 4.5).
        $terminaciones = app(TerminacionObreroService::class);
        $servidorDelActo = Servidor::with('contratoVigente')->findOrFail($movimiento->servidor_id);
        $datos = $terminaciones->completar($servidorDelActo, $movimiento->subtipoEfectivo(), [
            'fecha_presentacion_renuncia' => $movimiento->fecha_presentacion_renuncia?->toDateString(),
            'fecha_efectiva'              => $movimiento->fecha_efectiva?->toDateString(),
            ...$datos,
        ]);
        if ($movimiento->subtipoEfectivo() === SubtipoMovimientoPersonal::PRUEBA_NO_SUPERADA_CT) {
            $terminaciones->validarPeriodoDePrueba(
                $servidorDelActo->contratoVigente,
                $datos['fecha_efectiva'] ?? $movimiento->fecha_efectiva?->toDateString(),
            );
        }

        // La adenda, con lo corregido (fase 4.2).
        if ($movimiento->tipo_movimiento->esAdenda()) {
            $datos = app(ContratoCivilService::class)->completarAdenda(
                Servidor::with('contratoVigente')->findOrFail($movimiento->servidor_id),
                [
                    'puesto_destino_id'      => $movimiento->puesto_destino_id,
                    'unidad_destino_id'      => $movimiento->unidad_destino_id,
                    'remuneracion_propuesta' => $movimiento->remuneracion_propuesta,
                    'fecha_fin_propuesta'    => $movimiento->fecha_fin_propuesta?->toDateString(),
                    'fecha_efectiva'         => $movimiento->fecha_efectiva?->toDateString(),
                    ...$datos,
                ],
            );
        }

        // El cambio del contrato de trabajo, con lo corregido (fase 4.3).
        if ($movimiento->cambiaElContratoDeTrabajo()) {
            $datos = app(ContratoDeTrabajoService::class)->completar(
                Servidor::with('contratoVigente.puesto')->findOrFail($movimiento->servidor_id),
                $movimiento->tipo_movimiento,
                [
                    'puesto_destino_id'      => $movimiento->puesto_destino_id,
                    'unidad_destino_id'      => $movimiento->unidad_destino_id,
                    'remuneracion_propuesta' => $movimiento->remuneracion_propuesta,
                    'lugar_trabajo'          => $movimiento->lugar_trabajo,
                    'fecha_consentimiento'   => $movimiento->fecha_consentimiento?->toDateString(),
                    'fecha_efectiva'         => $movimiento->fecha_efectiva?->toDateString(),
                    ...$datos,
                ],
            );
        }

        // El ascenso se vuelve a comprobar con lo corregido, y su remuneración
        // se recalcula si cambió el puesto (fase 3.3).
        if ($movimiento->tipo_movimiento->asciende()) {
            $datos = app(AscensoService::class)->completar(
                Servidor::with('contratoVigente.puesto')->findOrFail($movimiento->servidor_id),
                [
                    'puesto_destino_id'         => $movimiento->puesto_destino_id,
                    'unidad_destino_id'         => $movimiento->unidad_destino_id,
                    'fecha_efectiva'            => $movimiento->fecha_efectiva?->toDateString(),
                    'partida_presupuestaria_id' => $movimiento->partida_presupuestaria_id,
                    ...$datos,
                ],
            );
        }

        $movimiento->update($datos);

        return $movimiento->fresh([
            'unidadOrigen', 'unidadDestino', 'puestoOrigen.cargo',
            'puestoDestino.cargo', 'partidaPresupuestaria', 'autorizadoPor',
        ]);
    }

    /**
     * El supuesto del provisional que el ingreso propone (LOSEP 17 b; fase
     * 3.2). Fuera de un provisional no significa nada, ni el acta del concurso
     * fuera del de prueba. Se puede dejar para el borrador —Reclutamiento no lo
     * sabe en el express—, pero no se suscribe sin él.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function conSupuestoDelIngreso(array $datos): array
    {
        $nombramiento = $datos['tipo_nombramiento_propuesto'] ?? null;
        $nombramiento = $nombramiento instanceof TipoNombramiento
            ? $nombramiento
            : TipoNombramiento::tryFrom((string) $nombramiento);

        $supuesto = $nombramiento === TipoNombramiento::PROVISIONAL
            ? SupuestoProvisional::tryFrom((string) ($datos['supuesto_provisional_propuesto'] ?? ''))
            : null;

        if ($supuesto === SupuestoProvisional::NIVEL_JERARQUICO_SUPERIOR && ! empty($datos['puesto_destino_id'])) {
            $puesto = Puesto::with('grupoOcupacional')->find($datos['puesto_destino_id']);

            if ($puesto && ! $puesto->esNivelJerarquicoSuperior()) {
                throw new ReglaNegocioException(
                    'El provisional del nivel jerárquico superior (LOSEP 17 b.4) es para un puesto de ese nivel, '
                        .'y el elegido no lo es.'
                );
            }
        }

        $datos['supuesto_provisional_propuesto'] = $supuesto?->value;

        if ($supuesto !== SupuestoProvisional::DE_PRUEBA) {
            $datos['acta_concurso_numero'] = null;
            $datos['acta_concurso_fecha'] = null;
        }

        return $datos;
    }

    /**
     * La remoción es del provisional cuyo supuesto terminó (b.1, b.3, b.4), y
     * el no superar la prueba, del de prueba (b.5) [diseño, 4.3; fase 3.2]. Los
     * provisionales de antes no dicen su supuesto: conservan las dos.
     *
     * Al ascendido no le vale ninguna de las dos (fase 3.3): si no supera la
     * prueba vuelve a su puesto anterior con el reintegro, no cesa.
     */
    private function validarCausalSegunSupuesto(Servidor $servidor, ?SubtipoMovimientoPersonal $subtipo): void
    {
        $vigente = $servidor->contratoVigente;

        if ($vigente?->tipo_nombramiento !== TipoNombramiento::PROVISIONAL || $vigente->supuesto_provisional === null) {
            return;
        }

        if ($vigente->supuesto_provisional === SupuestoProvisional::ASCENSO && in_array($subtipo, [
            SubtipoMovimientoPersonal::PERIODO_PRUEBA_NO_SUPERADO, SubtipoMovimientoPersonal::REMOCION,
        ], true)) {
            throw new ReglaNegocioException(
                'El servidor está en el período de prueba de un ascenso: si no lo supera, vuelve a su puesto '
                    .'anterior con su remuneración anterior (LOSEP Art. 17 b.5). Prepare el reintegro desde '
                    .'«Períodos de prueba».'
            );
        }

        $dePrueba = $vigente->supuesto_provisional === SupuestoProvisional::DE_PRUEBA;

        if ($subtipo === SubtipoMovimientoPersonal::PERIODO_PRUEBA_NO_SUPERADO && ! $dePrueba) {
            throw new ReglaNegocioException(
                'No superar el período de prueba es causal del provisional de prueba (LOSEP 17 b.5), y este es '
                    ."«{$vigente->supuesto_provisional->etiqueta()}»."
            );
        }

        if ($subtipo === SubtipoMovimientoPersonal::REMOCION && $dePrueba) {
            throw new ReglaNegocioException(
                'Al provisional de prueba no se le remueve: si no supera la prueba, cesa por esa causal '
                    .'(LOSEP 17 b.5).'
            );
        }
    }

    /**
     * Corregir el borrador de un traslado: el informe no se deja vacío, y el
     * puesto resultante sigue cumpliendo el Art. 35 (fase 3.1).
     *
     * @param  array<string, mixed>  $datos
     */
    private function revalidarTraslado(MovimientoPersonal $movimiento, array $datos): void
    {
        $vigente = Servidor::with('contratoVigente')->find($movimiento->servidor_id)?->contratoVigente;

        if (! $vigente || ! TrasladoService::aplica(
            $movimiento->tipo_movimiento, $movimiento->subtipoEfectivo(), $vigente->tipo_nombramiento
        )) {
            return;
        }

        $traslados = app(TrasladoService::class);

        if (array_key_exists('informe_uath_numero', $datos)) {
            $traslados->exigirInforme($datos['informe_uath_numero']);
        }

        if (array_key_exists('puesto_destino_id', $datos) || array_key_exists('unidad_destino_id', $datos)) {
            $puesto = $datos['puesto_destino_id'] ?? $movimiento->puesto_destino_id;
            $unidad = $datos['unidad_destino_id'] ?? $movimiento->unidad_destino_id;

            $traslados->validarDestino($vigente, $puesto ? (int) $puesto : null, $unidad ? (int) $unidad : null);
        }
    }

    /**
     * El instrumento lo decide el nombramiento que propone lo que crea un
     * vínculo, y el vigente en lo demás: todo acto de una autoridad electa es
     * acto de autoridad (fase 4.1).
     */
    private function instrumentoPara(
        TipoMovimientoPersonal $tipo,
        TipoNombramiento|string|null $propuesto,
        ?TipoNombramiento $vigente = null,
    ): string {
        $propuesto = $propuesto instanceof TipoNombramiento
            ? $propuesto
            : TipoNombramiento::tryFrom((string) $propuesto);

        return InstrumentoActo::para($tipo, $tipo->creaVinculo() ? $propuesto : ($vigente ?? $propuesto))->value;
    }

    /**
     * Al Prefecto y al Viceprefecto no se les registra un ingreso con acción de
     * personal: empiezan su período con la credencial del CNE (fase 4.1).
     */
    private static function impedirIngresoDeAutoridad(TipoMovimientoPersonal $tipo, TipoNombramiento|string|null $nombramiento): void
    {
        $nombramiento = $nombramiento instanceof TipoNombramiento
            ? $nombramiento
            : TipoNombramiento::tryFrom((string) $nombramiento);

        if ($tipo === TipoMovimientoPersonal::INGRESO && $nombramiento === TipoNombramiento::ELECCION_POPULAR) {
            throw new ReglaNegocioException(
                'La autoridad electa no ingresa con acción de personal: registre el «Inicio del período», con la '
                    .'credencial del CNE.'
            );
        }
    }

    /**
     * Corregir el borrador de una restitución: los datos de la causa siguen
     * obligatorios, y el puesto y el nombramiento no se tocan —vuelve al que
     * tenía—. Si cambia la fecha, la remoción del provisional se mueve con
     * ella mientras siga en borrador (fase 2.6).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function revalidarRestitucion(MovimientoPersonal $movimiento, array $datos): array
    {
        // Ni los de la contratación, que se copiaron del nombramiento que tenía
        // y se completan al aprobarla: el formulario los manda vacíos y los
        // borraría.
        unset(
            $datos['puesto_destino_id'], $datos['unidad_destino_id'], $datos['tipo_nombramiento_propuesto'],
            $datos['movimiento_relacionado_id'], $datos['cubre_movimiento_id'],
            $datos['numero_contrato'], $datos['remuneracion_propuesta'], $datos['partida_presupuestaria_id'],
            $datos['puede_marcar'], $datos['fecha_fin_propuesta'], $datos['lugar_trabajo'],
        );

        $campos = [
            'causa_judicial_numero', 'unidad_judicial', 'fecha_sentencia', 'fecha_ejecutoria',
            'fecha_providencia', 'remuneraciones_dejadas_de_percibir', 'fecha_efectiva',
        ];

        $restituciones = app(RestitucionService::class);
        $completos = [...$movimiento->only($campos), ...array_intersect_key($datos, array_flip($campos))];
        $restituciones->validarDatosJudiciales($completos, $movimiento->movimientoRelacionado);

        // La providencia o la sentencia del reintegro del obrero, no las dos
        // (fase 4.5b).
        $datos = [...$datos, ...$restituciones->ordenNormalizada($completos, $movimiento->movimientoRelacionado)];

        if (! empty($datos['fecha_efectiva'])) {
            $vispera = Carbon::parse($datos['fecha_efectiva'])->subDay()->toDateString();

            $restituciones->remociones($movimiento)
                ->where('estado', EstadoAccionPersonal::BORRADOR)
                ->each(fn (MovimientoPersonal $r) => $r->update(['fecha_efectiva' => $vispera]));
        }

        return $datos;
    }

    /**
     * Congela dónde estaba el servidor al momento de la acción: puesto y
     * unidad del vínculo vigente.
     *
     * Es la columna "situación actual" del documento impreso, y desde que un
     * traspaso o una prestación de servicios actualizan el contrato en vez de
     * duplicarlo, es también el único lugar donde queda constancia de dónde
     * venía la persona — el contrato ya solo refleja dónde está ahora. Sin
     * esto, el historial de movilidad se perdería.
     *
     * Un ingreso no tiene situación previa: se deja en null a propósito.
     */
    private function capturarSituacionActual(Servidor $servidor, array &$datos): void
    {
        $vigente = $servidor->contratoVigente;

        if (! $vigente) {
            return;
        }

        $datos['puesto_origen_id'] ??= $vigente->puesto_id;
        $datos['unidad_origen_id'] ??= $vigente->unidad_administrativa_id;

        // El grupo y la denominación del puesto, también congelados (fase
        // 7.1b): si después se reclasifica, el documento sigue diciendo con
        // cuáles se firmó.
        $datos['grupo_ocupacional_origen_id'] ??= $vigente->puesto?->grupo_ocupacional_id;
        $datos['cargo_origen_id'] ??= $vigente->puesto?->cargo_id;

        // La remuneración se toma del contrato, no del puesto: en Código del
        // Trabajo y Servicios Profesionales el puesto no define ninguna, y aun
        // en LOSEP el contrato puede llevar un monto ajustado.
        $datos['remuneracion_origen'] ??= $vigente->remuneracion;

        // La partida sale del vínculo, no del puesto: es la que realmente le
        // está pagando al servidor. Un ocasional y un permanente sobre la misma
        // plaza se imputan a partidas distintas, así que la del puesto solo
        // sirve de respaldo para los vínculos anteriores a esa distinción.
        $datos['partida_origen_id'] ??= $vigente->partida_presupuestaria_id
            ?? $vigente->puesto?->partida_presupuestaria_id;
    }

    /**
     * Exige subtipo donde corresponde, lo rechaza donde no aplica, y verifica
     * que pertenezca al tipo. Para los tipos planos legados devuelve su
     * equivalente para que la elegibilidad se evalúe igual.
     */
    private function resolverSubtipo(
        TipoMovimientoPersonal $tipo,
        array $datos
    ): ?SubtipoMovimientoPersonal {
        $valor = $datos['subtipo_movimiento'] ?? null;

        if (!$tipo->requiereSubtipo()) {
            if ($valor !== null) {
                throw new ReglaNegocioException(
                    "\"{$tipo->etiqueta()}\" no admite subtipo de acción de personal."
                );
            }

            return $tipo->subtipoEquivalente();
        }

        if ($valor === null) {
            throw new ReglaNegocioException(
                "\"{$tipo->etiqueta()}\" requiere que se especifique el subtipo de acción de personal."
            );
        }

        $subtipo = SubtipoMovimientoPersonal::tryFrom((string) $valor);

        if (!$subtipo || !in_array($subtipo, $tipo->subtiposPermitidos(), true)) {
            $permitidos = implode(', ', array_map(
                fn (SubtipoMovimientoPersonal $s) => $s->etiqueta(),
                $tipo->subtiposPermitidos()
            ));

            throw new ReglaNegocioException(
                "El subtipo indicado no corresponde a \"{$tipo->etiqueta()}\". Válidos: {$permitidos}."
            );
        }

        return $subtipo;
    }

    /**
     * Cuando hay subtipo, la regla de elegibilidad la fija el subtipo; si no,
     * el tipo. Esto cierra el hueco que tenían 'traslado' y 'traspaso', que
     * antes no pasaban por ninguna validación de nombramiento.
     */
    private function validarElegibilidad(
        Servidor $servidor,
        TipoMovimientoPersonal $tipoMovimiento,
        ?SubtipoMovimientoPersonal $subtipo
    ): void {
        $tipoNombramiento = $servidor->contratoVigente?->tipo_nombramiento;

        if (!$tipoNombramiento instanceof TipoNombramiento) {
            throw new ReglaNegocioException(
                'El servidor no tiene un contrato vigente con tipo de nombramiento definido.'
            );
        }

        $elegible = $subtipo
            ? $subtipo->elegiblePara($tipoNombramiento)
            : $tipoMovimiento->elegiblePara($tipoNombramiento);

        if (!$elegible) {
            $etiqueta = $subtipo?->etiqueta() ?? $tipoMovimiento->etiqueta();

            throw new ReglaNegocioException(
                "\"{$etiqueta}\" no aplica para el tipo de nombramiento vigente del servidor ({$tipoNombramiento->etiqueta()})."
            );
        }
    }

    /**
     * Cambiar a quién cubre un reemplazo pasa por las mismas reglas que
     * declararlo. Aceptarlo sin revalidar abriría por la puerta de la edición
     * justo lo que validarReemplazo() rechaza al crear.
     *
     * La ausencia que ya tenía enlazada no cuenta como "ya cubierta": se está
     * reemplazando ese enlace, no añadiendo otro.
     */
    private function revalidarReemplazo(MovimientoPersonal $movimiento, array $datos): void
    {
        if (! array_key_exists('cubre_movimiento_id', $datos)) {
            return;
        }

        $nuevo = $datos['cubre_movimiento_id'];

        // Desenlazar siempre se permite: deja de ser un reemplazo y pasa a ser
        // un ingreso ordinario, que es más restrictivo, no menos.
        if (empty($nuevo) || (int) $nuevo === (int) $movimiento->cubre_movimiento_id) {
            return;
        }

        $this->validarReemplazo($movimiento->servidor_id, $movimiento->tipo_movimiento, [
            ...$datos,
            'cubre_movimiento_id'         => $nuevo,
            'tipo_nombramiento_propuesto' => $datos['tipo_nombramiento_propuesto']
                ?? $movimiento->tipo_nombramiento_propuesto,
            'fecha_fin_propuesta'         => $datos['fecha_fin_propuesta']
                ?? $movimiento->fecha_fin_propuesta?->toDateString(),
        ]);
    }

    /**
     * Las reglas de la comisión se comprobaban solo al crearla. El borrador es
     * editable, así que alargar el período después las burlaba por completo:
     * bastaba registrar una comisión de 2 años y editarla a 10.
     *
     * Solo corre si la edición toca las fechas; se evalúa sobre el período
     * resultante, no sobre el enviado, porque la edición es parcial y puede
     * traer una sola de las dos.
     */
    private function revalidarPeriodoDeComision(MovimientoPersonal $movimiento, array $datos): void
    {
        $subtipo = $movimiento->subtipoEfectivo();

        if (! $subtipo?->esComisionDeServicios()) {
            return;
        }

        if (! array_key_exists('fecha_inicio', $datos) && ! array_key_exists('fecha_fin', $datos)) {
            return;
        }

        $servidor = $movimiento->servidor()->with('contratoVigente')->firstOrFail();

        $this->validarComisionDeServicios($servidor, $subtipo, [
            'fecha_inicio' => $datos['fecha_inicio'] ?? $movimiento->fecha_inicio?->toDateString(),
            'fecha_fin'    => $datos['fecha_fin'] ?? $movimiento->fecha_fin?->toDateString(),
        ], $movimiento->id);
    }

    /** La comisión y el intercambio van a otra entidad del Estado (fase 2.3). */
    private static function vaAOtraInstitucion(SubtipoMovimientoPersonal $subtipo): bool
    {
        return $subtipo->esComisionDeServicios() || $subtipo === SubtipoMovimientoPersonal::TRASLADO_ADMINISTRATIVO;
    }

    /**
     * Corregir las fechas de una licencia en borrador pasa por los mismos topes
     * que crearla, sin contarse a sí misma.
     */
    private function revalidarPeriodoDeLicencia(MovimientoPersonal $movimiento, array $datos): void
    {
        $causal = $movimiento->subtipoEfectivo();

        if (! $causal?->esLicenciaSinRemuneracion()) {
            return;
        }

        if (! array_key_exists('fecha_inicio', $datos) && ! array_key_exists('fecha_fin', $datos)) {
            return;
        }

        $this->validarLicenciaSinRemuneracion(
            $movimiento->servidor()->firstOrFail(),
            $causal,
            [
                'fecha_inicio' => $datos['fecha_inicio'] ?? $movimiento->fecha_inicio?->toDateString(),
                'fecha_fin'    => $datos['fecha_fin'] ?? $movimiento->fecha_fin?->toDateString(),
            ],
            $movimiento->id,
        );
    }

    /**
     * La licencia sin remuneración lleva siempre fechas y el tope de su causal
     * (LOSEP Art. 28; diseño, 4.4; TH 16; fase 2.2). Sin fechas no salía en
     * «Ausencias y reemplazos» y no se le podía contratar reemplazo.
     */
    private function validarLicenciaSinRemuneracion(
        Servidor $servidor,
        SubtipoMovimientoPersonal $causal,
        array $datos,
        ?int $excepto = null
    ): void {
        $etiqueta = 'La licencia por '.mb_strtolower($causal->etiqueta());

        if (empty($datos['fecha_inicio']) || empty($datos['fecha_fin'])) {
            throw new ReglaNegocioException(
                'La licencia sin remuneración lleva siempre fecha de inicio y fecha de fin.'
            );
        }

        $inicio = Carbon::parse($datos['fecha_inicio'])->startOfDay();
        $fin    = Carbon::parse($datos['fecha_fin'])->startOfDay();

        if ($fin->lt($inicio)) {
            throw new ReglaNegocioException('La fecha de fin no puede ser anterior a la de inicio.');
        }

        match ($causal) {
            SubtipoMovimientoPersonal::ASUNTOS_PARTICULARES => $this->validarTopeDeAsuntosParticulares($servidor, $inicio, $fin, $excepto),
            SubtipoMovimientoPersonal::ESTUDIOS_POSGRADO    => $this->validarServicioParaPosgrado($servidor, $inicio, $etiqueta),
            SubtipoMovimientoPersonal::CUIDADO_HIJOS        => $this->validarCuidadoDeHijos($servidor, $inicio, $fin, $etiqueta),
            // Servicio militar, reemplazo de un dignatario y candidatura duran lo
            // que dure su causa; la transitoria de obreros y autoridades no
            // tiene los topes de la LOSEP.
            default => null,
        };
    }

    /**
     * LOSEP 28 a: hasta 15 días con permiso del jefe y hasta 60 al año con la
     * autoridad nominadora. La acción la suscribe la autoridad, así que el tope
     * que se controla es el de 60 días por año calendario, sumando las de esta
     * causal que no se anularon.
     */
    private function validarTopeDeAsuntosParticulares(Servidor $servidor, Carbon $inicio, Carbon $fin, ?int $excepto): void
    {
        $previas = MovimientoPersonal::where('servidor_id', $servidor->id)
            ->where('tipo_movimiento', TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value)
            ->where('subtipo_movimiento', SubtipoMovimientoPersonal::ASUNTOS_PARTICULARES->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))
            ->whereNotNull('fecha_inicio')
            ->whereNotNull('fecha_fin')
            ->get(['id', 'fecha_inicio', 'fecha_fin']);

        for ($anio = $inicio->year; $anio <= $fin->year; $anio++) {
            $total = self::diasEnElAnio($inicio, $fin, $anio)
                + $previas->sum(fn (MovimientoPersonal $m) => self::diasEnElAnio($m->fecha_inicio, $m->fecha_fin, $anio));

            if ($total > 60) {
                throw new ReglaNegocioException(
                    "La licencia por asuntos particulares no puede pasar de 60 días al año: en {$anio} "
                        ."serían {$total} con esta (LOSEP Art. 28 a)."
                );
            }
        }
    }

    /** Días del período [inicio, fin] que caen en ese año, contando los dos extremos. */
    private static function diasEnElAnio(Carbon $inicio, Carbon $fin, int $anio): int
    {
        $desde = $inicio->copy()->max(Carbon::create($anio, 1, 1));
        $hasta = $fin->copy()->min(Carbon::create($anio, 12, 31));

        return $hasta->lt($desde) ? 0 : (int) $desde->diffInDays($hasta) + 1;
    }

    /** LOSEP 28 b: el posgrado exige 2 años de servicio a la fecha de inicio. */
    private function validarServicioParaPosgrado(Servidor $servidor, Carbon $inicio, string $etiqueta): void
    {
        if (! $servidor->fecha_ingreso_institucion
            || $servidor->fecha_ingreso_institucion->copy()->addYears(2)->gt($inicio)
        ) {
            throw new ReglaNegocioException(
                "{$etiqueta} exige al menos 2 años de servicio en la institución a la fecha de inicio (LOSEP Art. 28 b)."
            );
        }
    }

    /**
     * LOSEP 28 f: hasta 12 meses, dentro de los primeros 15 meses de vida del
     * hijo. El nacimiento sale de Cargas familiares; sin el hijo registrado no
     * hay contra qué medir.
     */
    private function validarCuidadoDeHijos(Servidor $servidor, Carbon $inicio, Carbon $fin, string $etiqueta): void
    {
        if ($fin->gt($inicio->copy()->addMonths(12)->subDay())) {
            throw new ReglaNegocioException("{$etiqueta} dura hasta 12 meses (LOSEP Art. 28 f).");
        }

        $cabe = CargaFamiliar::where('servidor_id', $servidor->id)
            ->where('parentesco', TipoParentesco::HIJO->value)
            ->where('estado', true)
            ->whereNotNull('fecha_nacimiento')
            ->get(['id', 'fecha_nacimiento'])
            ->contains(fn (CargaFamiliar $hijo) => $hijo->fecha_nacimiento->lte($inicio)
                && $fin->lte($hijo->fecha_nacimiento->copy()->addMonths(15)));

        if (! $cabe) {
            throw new ReglaNegocioException(
                "{$etiqueta} tiene que terminar dentro de los primeros 15 meses de vida de un hijo "
                    .'registrado en Cargas familiares (LOSEP Art. 28 f).'
            );
        }
    }

    /**
     * La regla legal de la comisión de servicios (fase 2.3; TH N5):
     *
     * - Con remuneración (LOSEP 30): 1 año de servicio a la fecha de inicio, y
     *   hasta 2 años.
     * - Sin remuneración (LOSEP 31): 1 año de servicio, hasta 6 años sumados en
     *   toda la carrera, y nunca para un puesto del nivel jerárquico superior.
     *
     * Las dos son de servidores de carrera, y eso ya lo dice la elegibilidad:
     * solo permanentes. Hasta la fase 2.3 se exigían 2 años de antigüedad —a
     * hoy, no a la fecha de inicio— y de 1 a 6 años de duración para las dos:
     * venía de la LOIP, que la Corte Constitucional anuló (52-25-IN/25).
     */
    private function validarComisionDeServicios(
        Servidor $servidor,
        SubtipoMovimientoPersonal $subtipo,
        array $datos,
        ?int $excepto = null
    ): void {
        $etiqueta = $subtipo->etiqueta();
        $articulo = $subtipo === SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION ? '30' : '31';

        if (empty($datos['fecha_inicio']) || empty($datos['fecha_fin'])) {
            throw new ReglaNegocioException(
                "La {$etiqueta} requiere fecha de inicio y fecha de fin."
            );
        }

        $inicio = Carbon::parse($datos['fecha_inicio'])->startOfDay();
        $fin    = Carbon::parse($datos['fecha_fin'])->startOfDay();

        if ($fin->lt($inicio)) {
            throw new ReglaNegocioException('La fecha de fin no puede ser anterior a la de inicio.');
        }

        if (!$servidor->fecha_ingreso_institucion) {
            throw new ReglaNegocioException(
                'El servidor no tiene fecha de ingreso a la institución registrada.'
            );
        }

        if ($servidor->fecha_ingreso_institucion->copy()->addYear()->gt($inicio)) {
            throw new ReglaNegocioException(
                "La {$etiqueta} exige al menos 1 año de servicio en la institución a la fecha de inicio (LOSEP Art. {$articulo})."
            );
        }

        if ($subtipo === SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION) {
            if ($fin->gt($inicio->copy()->addYears(2)->subDay())) {
                throw new ReglaNegocioException("La {$etiqueta} dura hasta 2 años (LOSEP Art. 30).");
            }

            return;
        }

        if ($servidor->contratoVigente?->puesto?->esNivelJerarquicoSuperior()) {
            throw new ReglaNegocioException(
                "La {$etiqueta} no es para un puesto del nivel jerárquico superior (LOSEP Art. 31)."
            );
        }

        // Seis años en toda la carrera: esta y las anteriores que no se
        // anularon, también las que se registraron con el tipo plano de antes.
        $previas = MovimientoPersonal::where('servidor_id', $servidor->id)
            ->where(fn ($q) => $q->where('subtipo_movimiento', SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value)
                ->orWhere(fn ($legado) => $legado->whereNull('subtipo_movimiento')
                    ->where('tipo_movimiento', TipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value)))
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))
            ->whereNotNull('fecha_inicio')
            ->whereNotNull('fecha_fin')
            ->get(['id', 'fecha_inicio', 'fecha_fin']);

        $dias = (int) $inicio->diffInDays($fin) + 1
            + (int) $previas->sum(fn (MovimientoPersonal $m) => $m->fecha_inicio->diffInDays($m->fecha_fin) + 1);

        $tope = (int) $inicio->diffInDays($inicio->copy()->addYears(6));

        if ($dias > $tope) {
            throw new ReglaNegocioException(
                "La {$etiqueta} no puede pasar de 6 años en toda la carrera: con esta serían "
                    ."{$dias} días, y el tope es de {$tope} (LOSEP Art. 31)."
            );
        }
    }

    /**
     * El movimiento previo solo tiene sentido como el eslabón "cesación →
     * ingreso": debe ser una cesación del mismo servidor y estar ya registrada
     * (una cesación en borrador no habilita nada todavía).
     */
    private function validarMovimientoPrevio(int $servidorId, int $movimientoPrevioId): void
    {
        $previo = MovimientoPersonal::find($movimientoPrevioId);

        if (!$previo || $previo->servidor_id !== $servidorId) {
            throw new ReglaNegocioException(
                'La acción de personal previa indicada no corresponde a este servidor.'
            );
        }

        if (!$previo->subtipoEfectivo()?->cierraVinculo()) {
            throw new ReglaNegocioException(
                'La acción de personal previa debe ser una Cesación de Funciones.'
            );
        }

        if (!in_array($previo->estado, [
            EstadoAccionPersonal::REGISTRADA,
            EstadoAccionPersonal::NOTIFICADA,
        ], true)) {
            throw new ReglaNegocioException(
                'La Cesación de Funciones previa debe estar registrada antes de encadenar el ingreso.'
            );
        }
    }

    /**
     * Reglas del enlace de reemplazo: este ingreso cubre el hueco que dejó una
     * comisión de servicios o una licencia sin remuneración.
     *
     * Se valida al crear porque es aquí donde el enlace se declara — el
     * contrato del reemplazo no existe todavía. Y cada regla evita una plaza
     * mal contada: sin ellas se podría colgar un nombramiento permanente de
     * una ausencia temporal, duplicar reemplazos sobre la misma ausencia, o
     * dejar al suplente trabajando después de que el titular regresó.
     */
    private function validarReemplazo(
        int $servidorId,
        TipoMovimientoPersonal $tipo,
        array $datos
    ): void {
        if (! $tipo->creaVinculo()) {
            throw new ReglaNegocioException(
                'Solo un Ingreso y Vinculación puede cubrir una ausencia temporal.'
            );
        }

        $ausencia = MovimientoPersonal::find((int) $datos['cubre_movimiento_id']);

        if (! $ausencia || ! $ausencia->esAusenciaTemporal()) {
            throw new ReglaNegocioException(
                'La acción que se pretende cubrir no es una comisión de servicios '
                    .'ni una licencia sin remuneración.'
            );
        }

        if ($ausencia->servidor_id === $servidorId) {
            throw new ReglaNegocioException(
                'Un servidor no puede cubrir su propia ausencia.'
            );
        }

        if ($ausencia->esAusenciaDeJefatura()) {
            throw new ReglaNegocioException(
                'La ausencia de una jefatura se cubre con subrogación o encargo, no con un reemplazo '
                    .'contratado (TH 19). Regístrela desde Subrogaciones.'
            );
        }

        if (! in_array($ausencia->estado, [
            EstadoAccionPersonal::REGISTRADA,
            EstadoAccionPersonal::NOTIFICADA,
        ], true)) {
            throw new ReglaNegocioException(
                'La ausencia debe estar registrada antes de contratar quien la cubra.'
            );
        }

        // El reemplazo es por definición transitorio: dura lo que dura la
        // ausencia. Un permanente o un provisional sobrevivirían al regreso
        // del titular y dejarían el puesto con dos ocupantes.
        $nombramiento = $datos['tipo_nombramiento_propuesto'] ?? null;
        $nombramiento = $nombramiento instanceof TipoNombramiento
            ? $nombramiento
            : ($nombramiento ? TipoNombramiento::tryFrom((string) $nombramiento) : null);

        $temporales = [
            TipoNombramiento::SERVICIOS_OCASIONALES,
            TipoNombramiento::SERVICIOS_PROFESIONALES,
        ];

        if (! in_array($nombramiento, $temporales, true)) {
            throw new ReglaNegocioException(
                'Una ausencia temporal solo se cubre con Servicios Ocasionales o '
                    .'Servicios Profesionales.'
            );
        }

        if ($this->ausenciaYaCubierta($ausencia)) {
            throw new ReglaNegocioException(
                'Esta ausencia ya tiene un reemplazo vigente. Cese el actual antes '
                    .'de registrar otro.'
            );
        }

        $this->validarPlazoDelReemplazo($ausencia, $datos);
    }

    /**
     * Una ausencia se cubre con un reemplazo a la vez. Cuentan tanto los
     * contratos ya materializados como los ingresos todavía en trámite: si no,
     * dos borradores simultáneos pasarían el control y el segundo reventaría
     * recién contra el índice único, con un error de base de datos en vez de
     * uno de negocio.
     */
    private function ausenciaYaCubierta(MovimientoPersonal $ausencia): bool
    {
        $conContrato = $ausencia->contratosReemplazo()
            ->where('estado', EstadoContrato::VIGENTE->value)
            ->exists();

        if ($conContrato) {
            return true;
        }

        return $ausencia->reemplazos()
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->exists();
    }

    /**
     * El reemplazo no puede durar más que la ausencia: al regresar el titular
     * recupera su puesto, y dos contratos vigentes sobre la misma plaza es
     * exactamente lo que el control de vacantes existe para impedir.
     */
    private function validarPlazoDelReemplazo(MovimientoPersonal $ausencia, array $datos): void
    {
        // Si el titular ya tiene su reintegro, la ausencia termina antes.
        $finAusencia = $ausencia->finEfectivo()?->toDateString();

        if (! $finAusencia) {
            return;
        }

        $finReemplazo = $datos['fecha_fin_propuesta'] ?? null;

        if ($finReemplazo && Carbon::parse($finReemplazo)->toDateString() > $finAusencia) {
            throw new ReglaNegocioException(
                "El reemplazo no puede extenderse más allá del {$finAusencia}, "
                    .'que es cuando termina la ausencia que cubre.'
            );
        }
    }

    /**
     * Si Talento Humano no se pronunció, se sugiere el valor que corresponde al
     * tipo de nombramiento propuesto. Sin nombramiento propuesto (acciones que
     * no crean vínculo) se deja null: esta acción no opina sobre la marcación
     * y el contrato conserva la suya.
     */
    private function resolverPuedeMarcar(array $datos): ?bool
    {
        $propuesto = $datos['tipo_nombramiento_propuesto'] ?? null;

        $nombramiento = $propuesto instanceof TipoNombramiento
            ? $propuesto
            : ($propuesto !== null ? TipoNombramiento::tryFrom((string) $propuesto) : null);

        // Hay modalidades que no marcan nunca —servicios profesionales, libre
        // nombramiento y elección popular—. Se fuerza a falso aunque el cliente
        // mande lo contrario: es una restricción, no un valor sugerido, y no
        // puede depender de que el formulario se comporte bien.
        if ($nombramiento !== null && ! $nombramiento->admiteMarcacion()) {
            return false;
        }

        if (array_key_exists('puede_marcar', $datos) && $datos['puede_marcar'] !== null) {
            return (bool) $datos['puede_marcar'];
        }

        // Sin nombramiento propuesto (acciones que no crean vínculo) se deja
        // null: esta acción no opina sobre la marcación y el contrato conserva
        // la suya.
        return $nombramiento?->puedeMarcarPorDefecto();
    }

    /**
     * En el contrato civil la ficha médica es opcional [TH 25; fase 4.2]: quien
     * tiene Servicios Profesionales no es servidor. Se puede marcar igual.
     */
    private function requiereDictamenPorDefecto(
        TipoMovimientoPersonal $tipo,
        ?SubtipoMovimientoPersonal $subtipo,
        InstrumentoActo $instrumento,
    ): bool {
        if ($instrumento === InstrumentoActo::CONTRATO_CIVIL) {
            return false;
        }

        return $subtipo
            ? $subtipo->requiereDictamenMedicoPorDefecto()
            : $tipo->requiereDictamenMedicoPorDefecto();
    }
}
