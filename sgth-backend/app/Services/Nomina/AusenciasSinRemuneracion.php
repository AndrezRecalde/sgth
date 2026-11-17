<?php

namespace App\Services\Nomina;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoSancion;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Expediente\MovimientoPersonal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Quién estuvo sin remuneración, y cuándo: la licencia y la comisión sin
 * remuneración (LOSEP Arts. 28 y 31), la licencia del Consejo que no la
 * conserva y la suspensión temporal sin goce de remuneración (LOSEP Art. 43 c).
 *
 * Vivía dentro de `NovedadesDelMes`, que cuenta sus días en el mes. La masa
 * salarial hace la misma pregunta para un día (docs/diseno-masa-salarial.md,
 * D4), y dos versiones de ella acabarían respondiendo distinto.
 *
 * @phpstan-type Ausencia array{
 *     clave: string, tipo: 'ausencia'|'suspension', servidor_id: int,
 *     acto: MovimientoPersonal, desde: CarbonInterface, hasta: ?CarbonInterface
 * }
 */
final class AusenciasSinRemuneracion
{
    /** Las que surtieron efecto: lo que sigue en trámite todavía no rige. */
    private const EMITIDAS = [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA];

    /**
     * Las que tocan el período, del primer día al último, con las fechas en
     * que duran ellas: el que las use las recorta a lo suyo. Sin `hasta`, la
     * licencia sigue abierta.
     *
     * @return Collection<int, Ausencia>
     */
    public function entre(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        return collect()
            ->merge($this->licenciasYComisiones($desde, $hasta))
            ->merge($this->suspensiones($desde, $hasta))
            ->values();
    }

    /**
     * Hasta su fin, o hasta la víspera del reintegro, si volvió antes (fase
     * 2.4).
     *
     * @return list<Ausencia>
     */
    private function licenciasYComisiones(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $actos = MovimientoPersonal::query()
            ->esAusenciaTemporal()
            ->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS))
            ->whereDate('fecha_inicio', '<=', $hasta->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $desde->toDateString()))
            ->with(['servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula', 'reintegro'])
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get()
            ->filter(fn (MovimientoPersonal $acto) => $this->esSinRemuneracion($acto));

        $ausencias = [];

        foreach ($actos as $acto) {
            $fin = $acto->finEfectivo();

            // El reintegro puede haberla cerrado antes de que empezara el período.
            if (! $this->seCruzan($acto->fecha_inicio, $fin, $desde, $hasta)) {
                continue;
            }

            $ausencias[] = [
                'clave'       => "sin_remuneracion:movimiento:{$acto->id}",
                'tipo'        => 'ausencia',
                'servidor_id' => $acto->servidor_id,
                'acto'        => $acto,
                'desde'       => $acto->fecha_inicio,
                'hasta'       => $fin,
            ];
        }

        return $ausencias;
    }

    private function esSinRemuneracion(MovimientoPersonal $acto): bool
    {
        return match (true) {
            $acto->tipo_movimiento === TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION => true,
            $acto->tipo_movimiento === TipoMovimientoPersonal::LICENCIA_CONSEJO          => $acto->con_remuneracion === false,
            default => $acto->subtipoEfectivo() === SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION
                || ($acto->subtipo_movimiento === null && $acto->tipo_movimiento === TipoMovimientoPersonal::COMISION_SIN_REMUNERACION),
        };
    }

    /**
     * Los días calendario desde que rige, como el aviso que ya recibe
     * Financiero (#344). Dura hasta treinta días (LOSEP Art. 43 c): la que rige
     * hasta dos meses antes todavía puede tocar el período.
     *
     * @return list<Ausencia>
     */
    private function suspensiones(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $sanciones = SancionDisciplinaria::query()
            ->where('tipo_sancion', TipoSancion::SUSPENSION->value)
            // La que una sentencia dejó sin efecto ya no se descuenta.
            ->whereNull('anulada_en')
            ->whereHas('movimientoPersonal', fn (Builder $q) => $q
                ->whereIn('estado', array_map(fn ($e) => $e->value, self::EMITIDAS))
                ->whereBetween('fecha_efectiva', [
                    CarbonImmutable::parse($desde->toDateString())->subMonths(2)->toDateString(),
                    $hasta->toDateString(),
                ]))
            ->with('movimientoPersonal.servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get();

        $ausencias = [];

        foreach ($sanciones as $sancion) {
            $acto = $sancion->movimientoPersonal;
            $inicio = $sancion->fecha_efectiva ?? $acto->fecha_efectiva;
            $dias = (int) $sancion->dias_suspension;

            if (! $inicio || $dias < 1) {
                continue;
            }

            $fin = $inicio->copy()->addDays($dias - 1);

            if (! $this->seCruzan($inicio, $fin, $desde, $hasta)) {
                continue;
            }

            $ausencias[] = [
                'clave'       => "suspension:movimiento:{$acto->id}",
                'tipo'        => 'suspension',
                'servidor_id' => $acto->servidor_id,
                'acto'        => $acto,
                'desde'       => $inicio,
                'hasta'       => $fin,
            ];
        }

        return $ausencias;
    }

    /** ¿La ausencia, abierta si no tiene fin, toca el período? */
    private function seCruzan(CarbonInterface $inicio, ?CarbonInterface $fin, CarbonInterface $desde, CarbonInterface $hasta): bool
    {
        return $inicio->toDateString() <= $hasta->toDateString()
            && ($fin === null || $fin->toDateString() >= $desde->toDateString());
    }
}
