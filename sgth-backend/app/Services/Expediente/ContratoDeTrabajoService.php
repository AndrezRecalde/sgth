<?php

namespace App\Services\Expediente;

use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;

/**
 * El contrato de trabajo de un obrero (diseño de Acciones de Personal, 9.15;
 * TH N8, 14 y 15; fase 4.3).
 *
 * A los obreros los rige el Código del Trabajo (Constitución Arts. 229 y
 * 326.16), no la LOSEP: no se les emite acción de personal. Lo que cambia de
 * su contrato lo cambia una adenda que firman el empleador y el trabajador, y
 * aquí se registra con la hoja de registro interno.
 *
 * - **Cambio de ocupación** (CT 192): a otro puesto del catálogo de obreros,
 *   con el consentimiento escrito del trabajador; sin él, el cambio equivale a
 *   un despido intempestivo.
 * - **Cambio de lugar**: a otra unidad, en un puesto de la misma denominación,
 *   o a otro lugar de trabajo.
 * - **Incremento** individual: un monto mayor que el actual.
 *
 * En los tres la remuneración nunca baja. Lo que no cambia queda vacío en la
 * acción.
 */
class ContratoDeTrabajoService
{
    /**
     * Valida el cambio y deja en él solo lo que cambia, con el lugar que tenía
     * el obrero. Al preparar y al corregir el borrador.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function completar(Servidor $servidor, TipoMovimientoPersonal $tipo, array $datos): array
    {
        $vigente = $this->exigirContratoDeTrabajo($servidor->contratoVigente);

        $puestoId = ! empty($datos['puesto_destino_id']) ? (int) $datos['puesto_destino_id'] : null;
        $unidadId = ! empty($datos['unidad_destino_id']) ? (int) $datos['unidad_destino_id'] : null;

        [$puesto, $unidad] = match ($tipo) {
            TipoMovimientoPersonal::CAMBIO_DENOMINACION => $this->destinoDeLaOcupacion($vigente, $puestoId, $unidadId),
            TipoMovimientoPersonal::CAMBIO_LUGAR        => $this->destinoDelLugar($vigente, $puestoId, $unidadId),
            default                                     => [null, null],
        };

        $remuneracion = $tipo === TipoMovimientoPersonal::CAMBIO_LUGAR
            ? null
            : $this->remuneracionNueva($vigente, $datos['remuneracion_propuesta'] ?? null);

        $lugar = $tipo === TipoMovimientoPersonal::CAMBIO_LUGAR && filled($datos['lugar_trabajo'] ?? null)
            ? trim((string) $datos['lugar_trabajo'])
            : null;

        if ($lugar !== null && $lugar === $vigente->lugar_trabajo) {
            $lugar = null;
        }

        $consentimiento = null;

        if ($tipo === TipoMovimientoPersonal::CAMBIO_DENOMINACION) {
            $consentimiento = $this->consentimiento($datos['fecha_consentimiento'] ?? null, $datos['fecha_efectiva'] ?? null);
        }

        if ($tipo === TipoMovimientoPersonal::CAMBIO_LUGAR && ! $puesto && $lugar === null) {
            throw new ReglaNegocioException(
                'El cambio de lugar no cambia nada: indique la unidad y el puesto, o el lugar de trabajo.'
            );
        }

        if ($tipo === TipoMovimientoPersonal::INCREMENTO_REMUNERACION && $remuneracion === null) {
            throw new ReglaNegocioException(
                'Indique la remuneración nueva, mayor que la actual'
                    .($vigente->remuneracion !== null ? ' ($ '.number_format((float) $vigente->remuneracion, 2).')' : '')
                    .'.'
            );
        }

        return [
            ...$datos,
            'puesto_destino_id'      => $puesto?->id,
            'unidad_destino_id'      => $unidad,
            'remuneracion_propuesta' => $remuneracion,
            'lugar_trabajo'          => $lugar,
            'lugar_trabajo_origen'   => $vigente->lugar_trabajo,
            'fecha_consentimiento'   => $consentimiento,
            // Lo que no significa nada en un cambio: no hay contrato nuevo.
            'tipo_nombramiento_propuesto' => null,
            'numero_contrato'             => null,
            'fecha_fin_propuesta'         => null,
            'cubre_movimiento_id'         => null,
        ];
    }

    /**
     * Lo mismo, otra vez, al registrarlo: el contrato, el puesto o su plaza
     * pueden haber cambiado desde el borrador.
     */
    public function validarAlRegistrar(MovimientoPersonal $movimiento): void
    {
        $vigente = $this->exigirContratoDeTrabajo(
            Servidor::with('contratoVigente')->find($movimiento->servidor_id)?->contratoVigente
        );

        $puestoId = $movimiento->puesto_destino_id ? (int) $movimiento->puesto_destino_id : null;
        $unidadId = $movimiento->unidad_destino_id ? (int) $movimiento->unidad_destino_id : null;

        if ($puestoId) {
            $movimiento->tipo_movimiento === TipoMovimientoPersonal::CAMBIO_LUGAR
                ? $this->destinoDelLugar($vigente, $puestoId, $unidadId)
                : $this->destinoDeLaOcupacion($vigente, $puestoId, $unidadId);
        }

        if ($movimiento->remuneracion_propuesta !== null) {
            $this->remuneracionNueva($vigente, $movimiento->remuneracion_propuesta, true);
        }

        // Si rige ya, `aplicarCambioDelObrero()` lo comprueba al aplicarlo.
        if ($puestoId && ! $movimiento->rigeEn(now()->toDateString())) {
            app(ContratoServidorService::class)->validarVacante(
                $puestoId, TipoNombramiento::CODIGO_TRABAJO->value, $vigente->id, null, $movimiento->id,
            );
        }
    }

    /**
     * El cambio lo firmaron el empleador y el trabajador en una adenda, y su
     * número va en «Número de resolución». Se pide al suscribir.
     */
    public function exigirAdenda(MovimientoPersonal $movimiento): void
    {
        if (! $movimiento->cambiaElContratoDeTrabajo() || filled($movimiento->resolucion_numero)) {
            return;
        }

        throw new ReglaNegocioException(
            'No se puede suscribir sin el número de la adenda que firmaron el empleador y el trabajador, en '
                .'«Adenda N.º».'
        );
    }

    private function exigirContratoDeTrabajo(?ContratoServidor $vigente): ContratoServidor
    {
        if (! $vigente || $vigente->tipo_nombramiento !== TipoNombramiento::CODIGO_TRABAJO) {
            throw new ReglaNegocioException(
                'El cambio es del contrato de trabajo de un obrero, y el servidor no lo tiene vigente.'
            );
        }

        return $vigente;
    }

    /**
     * El puesto de la ocupación nueva: del catálogo de obreros, activo, de la
     * unidad indicada y de otra denominación. Con la misma, lo que cambia es
     * el lugar.
     *
     * @return array{0: Puesto, 1: int}
     */
    private function destinoDeLaOcupacion(ContratoServidor $vigente, ?int $puestoId, ?int $unidadId): array
    {
        if (! $puestoId) {
            throw new ReglaNegocioException('Indique el puesto de la ocupación nueva.');
        }

        $puesto = $this->puestoDelCatalogo($puestoId, $unidadId);

        if ((int) $puesto->id === (int) $vigente->puesto_id
            || ($vigente->puesto && (int) $puesto->cargo_id === (int) $vigente->puesto->cargo_id)
        ) {
            throw new ReglaNegocioException(
                'El puesto es de la misma denominación que el actual: no hay cambio de ocupación. Si cambia solo '
                    .'la unidad, es un cambio de lugar.'
            );
        }

        return [$puesto, (int) $puesto->unidad_administrativa_id];
    }

    /**
     * El puesto del lugar nuevo, si cambia: del catálogo de obreros, activo,
     * de la unidad indicada y de la misma denominación. Con otra, es un cambio
     * de ocupación, que pide el consentimiento del trabajador.
     *
     * @return array{0: ?Puesto, 1: ?int}
     */
    private function destinoDelLugar(ContratoServidor $vigente, ?int $puestoId, ?int $unidadId): array
    {
        if (! $puestoId || $puestoId === (int) $vigente->puesto_id) {
            if ($unidadId && $unidadId !== (int) $vigente->unidad_administrativa_id) {
                throw new ReglaNegocioException('Indique el puesto de la unidad a la que pasa.');
            }

            return [null, null];
        }

        $puesto = $this->puestoDelCatalogo($puestoId, $unidadId);

        if ($vigente->puesto && (int) $puesto->cargo_id !== (int) $vigente->puesto->cargo_id) {
            throw new ReglaNegocioException(
                'El puesto es de otra denominación: eso es un cambio de ocupación, que pide el consentimiento '
                    .'escrito del trabajador (Código del Trabajo, Art. 192).'
            );
        }

        return [$puesto, (int) $puesto->unidad_administrativa_id];
    }

    private function puestoDelCatalogo(int $puestoId, ?int $unidadId): Puesto
    {
        $puesto = Puesto::with(['cargo:id,nombre', 'unidadAdministrativa:id,nombre'])->find($puestoId);

        if (! $puesto) {
            throw new ReglaNegocioException('El puesto indicado no existe.');
        }

        $cual = $puesto->cargo?->nombre ? " ({$puesto->cargo->nombre})" : '';

        if ($puesto->regimen_laboral !== 'codigo_trabajo') {
            throw new ReglaNegocioException("El puesto{$cual} no es del catálogo de obreros.");
        }

        if (! $puesto->activo) {
            throw new ReglaNegocioException("El puesto{$cual} está inactivo.");
        }

        if ($unidadId && (int) $puesto->unidad_administrativa_id !== $unidadId) {
            throw new ReglaNegocioException(
                "El puesto{$cual} no es de la unidad indicada: es de "
                    .($puesto->unidadAdministrativa?->nombre ?? 'otra unidad').'.'
            );
        }

        return $puesto;
    }

    /**
     * La remuneración nueva, si cambia: nunca menor que la actual. Igual a la
     * actual no es un cambio, y queda vacía; al registrar, ya fijada, se
     * comprueba que siga sin bajar.
     */
    private function remuneracionNueva(ContratoServidor $vigente, mixed $valor, bool $fijada = false): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $nueva = round((float) $valor, 2);
        $actual = $vigente->remuneracion !== null ? round((float) $vigente->remuneracion, 2) : null;

        if ($nueva <= 0) {
            throw new ReglaNegocioException('La remuneración nueva tiene que ser mayor que cero.');
        }

        if ($actual !== null && $nueva < $actual) {
            throw new ReglaNegocioException(
                'La remuneración de un obrero nunca baja: hoy es $ '.number_format($actual, 2)
                    .' y el cambio la deja en $ '.number_format($nueva, 2).'.'
            );
        }

        return ! $fijada && $actual !== null && $nueva === $actual ? null : $nueva;
    }

    /**
     * La fecha del consentimiento escrito del trabajador (CT 192): no futura,
     * y no después de que rija el cambio.
     */
    private function consentimiento(mixed $fecha, mixed $rige): string
    {
        if (blank($fecha)) {
            throw new ReglaNegocioException(
                'Indique la fecha del consentimiento escrito del trabajador: sin él, el cambio de ocupación '
                    .'equivale a un despido intempestivo (Código del Trabajo, Art. 192).'
            );
        }

        $fecha = Carbon::parse($fecha)->toDateString();

        if ($fecha > now()->toDateString()) {
            throw new ReglaNegocioException('La fecha del consentimiento no puede ser futura.');
        }

        if (filled($rige) && $fecha > Carbon::parse($rige)->toDateString()) {
            throw new ReglaNegocioException('El consentimiento del trabajador es previo al cambio: no puede ser posterior a la fecha en que rige.');
        }

        return $fecha;
    }
}
