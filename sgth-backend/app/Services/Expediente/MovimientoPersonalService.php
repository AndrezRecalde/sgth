<?php

namespace App\Services\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\ClaseAccionPersonal;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoParentesco;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;

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
    public static function impedirTerminacionDeProtegida(int $servidorId): void
    {
        if (ProteccionMaternidad::consta($servidorId)) {
            throw new ReglaNegocioException(
                ProteccionMaternidad::aviso().' La terminación unilateral no procede.'
            );
        }
    }

    public function registrar(int $servidorId, array $datos): MovimientoPersonal
    {
        // Aquí y no en el controlador: por este método crean sus borradores
        // también Disciplinario y Reclutamiento.
        TramiteSobreSiMismo::impedir($servidorId);

        $servidor = Servidor::with('contratoVigente.puesto')->findOrFail($servidorId);
        $tipoMovimiento = TipoMovimientoPersonal::from($datos['tipo_movimiento']);

        $subtipo = $this->resolverSubtipo($tipoMovimiento, $datos);

        if ($tipoMovimiento->esAccionDePersonal()) {
            $this->validarElegibilidad($servidor, $tipoMovimiento, $subtipo);
        }

        if ($subtipo === SubtipoMovimientoPersonal::TERMINACION_UNILATERAL) {
            self::impedirTerminacionDeProtegida($servidorId);
        }

        if ($subtipo?->esComisionDeServicios()) {
            $this->validarComisionDeServicios($servidor, $subtipo, $datos);
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

        // Default de dictamen médico por tipo/subtipo. Si el llamador lo trae
        // explícito (Talento Humano lo editó, o el ingreso de reclutamiento que
        // ya tiene su dictamen en mano), se respeta.
        $datos['requiere_dictamen_medico'] = array_key_exists('requiere_dictamen_medico', $datos)
            ? (bool) $datos['requiere_dictamen_medico']
            : $this->requiereDictamenPorDefecto($tipoMovimiento, $subtipo);

        $datos['puede_marcar'] = $this->resolverPuedeMarcar($datos);

        // Todo lo que entra por aquí es un acto: nace en BORRADOR y pasa por
        // MovimientoPersonalStateService::transicionar() antes de considerarse
        // registrado. Hasta la fase 1.2 los movimientos históricos genéricos
        // —cambio de régimen, novedad de contrato— se saltaban esto y nacían
        // con el default de BD (REGISTRADA); eran bitácora, y hoy viven en
        // `eventos_vinculo`.
        $datos['estado']    = EstadoAccionPersonal::BORRADOR;
        $datos['categoria'] = CategoriaEventoVinculo::ACCION_DE_PERSONAL;

        $movimiento = MovimientoPersonal::create($datos);

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
        $this->revalidarReemplazo($movimiento, $datos);

        $movimiento->update($datos);

        return $movimiento->fresh([
            'unidadOrigen', 'unidadDestino', 'puestoOrigen.cargo',
            'puestoDestino.cargo', 'partidaPresupuestaria', 'autorizadoPor',
        ]);
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
        ]);
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
     * Antigüedad ≥ 2 años en la institución y duración de 1 a 6 años. Aplica
     * por igual a la comisión con y sin remuneración — confirmado con Talento
     * Humano (2026-07-27).
     */
    private function validarComisionDeServicios(
        Servidor $servidor,
        SubtipoMovimientoPersonal $subtipo,
        array $datos
    ): void {
        $etiqueta = $subtipo->etiqueta();

        if (empty($datos['fecha_inicio']) || empty($datos['fecha_fin'])) {
            throw new ReglaNegocioException(
                "La {$etiqueta} requiere fecha de inicio y fecha de fin."
            );
        }

        if (!$servidor->fecha_ingreso_institucion) {
            throw new ReglaNegocioException(
                'El servidor no tiene fecha de ingreso a la institución registrada.'
            );
        }

        // diffInYears() devuelve float en Carbon 3, así que exactamente 2 años
        // cumple el umbral "2 años o más" que fijó Talento Humano.
        $aniosAntiguedad = Carbon::parse($servidor->fecha_ingreso_institucion)
            ->diffInYears(now());

        if ($aniosAntiguedad < 2) {
            throw new ReglaNegocioException(
                "La {$etiqueta} requiere al menos 2 años de antigüedad en la institución."
            );
        }

        $duracionAnios = Carbon::parse($datos['fecha_inicio'])
            ->diffInYears(Carbon::parse($datos['fecha_fin']));

        if ($duracionAnios < 1 || $duracionAnios > 6) {
            throw new ReglaNegocioException(
                "La {$etiqueta} debe durar entre 1 y 6 años."
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
        $finAusencia = $ausencia->fecha_fin?->toDateString();

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

    private function requiereDictamenPorDefecto(
        TipoMovimientoPersonal $tipo,
        ?SubtipoMovimientoPersonal $subtipo
    ): bool {
        return $subtipo
            ? $subtipo->requiereDictamenMedicoPorDefecto()
            : $tipo->requiereDictamenMedicoPorDefecto();
    }
}
