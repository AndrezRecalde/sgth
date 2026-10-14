<?php

namespace App\Services\Tic\Reportes;

use App\Enums\EstadoBien;
use App\Enums\TipoMantenimiento;
use App\Models\InventarioTi\BienInformatico;
use Illuminate\Database\Eloquent\Builder;

/**
 * La obsolescencia del parque (fase 4.2): qué equipos en servicio ya
 * cumplieron la vida útil de su tipo o la cumplen en los próximos 12 meses,
 * con su edad y los correctivos del último año, para planificar el reemplazo.
 * El resumen por tipo dice además cuántos no tienen fin de vida útil: les
 * falta la fecha de compra o su tipo no tiene todavía años de vida útil (TIC
 * los iba a definir y la carga del Excel los dejó en 0).
 */
final class ReporteObsolescenciaService
{
    /** Meses hacia adelante para avisar del fin de la vida útil. */
    private const MESES_AVISO = 12;

    /**
     * @param  array{tipo_bien_id?: int|null}  $filtros
     * @return array{corte: string, resumen: list<array<string, mixed>>, equipos: list<array<string, mixed>>, totales: array<string, int>}
     */
    public function generar(array $filtros): array
    {
        $hoy = today();
        $limite = $hoy->copy()->addMonths(self::MESES_AVISO);

        $bienes = BienInformatico::with([
            'tipo:id,nombre,anios_vida_util', 'marca:id,nombre',
            'asignacionVigente.servidor:id,nombre,apellido,unidad_administrativa_id', 'asignacionVigente.servidor.unidadAdministrativa:id,nombre',
            'unidadAdministrativa:id,nombre',
        ])
            ->withCount(['mantenimientos as correctivos' => fn (Builder $q) => $q->vigentes()
                ->where('tipo_mantenimiento', TipoMantenimiento::CORRECTIVO->value)
                ->whereDate('fecha_mantenimiento', '>=', $hoy->copy()->subYear())])
            ->whereNull('bien_padre_id')
            ->where('estado_operativo', '<>', EstadoBien::DADO_DE_BAJA->value)
            ->when($filtros['tipo_bien_id'] ?? null, fn (Builder $q, $tipo) => $q->where('tipo_bien_id', $tipo))
            ->get();

        $situacion = fn (BienInformatico $b) => match (true) {
            $b->fecha_fin_vida_util === null => 'sin_fecha',
            $b->fecha_fin_vida_util->lt($hoy) => 'cumplida',
            $b->fecha_fin_vida_util->lte($limite) => 'proxima',
            default => 'vigente',
        };

        $resumen = $bienes->groupBy(fn (BienInformatico $b) => $b->tipo?->nombre ?? 'Sin tipo')
            ->map(fn ($grupo, string $tipo) => [
                'tipo'            => $tipo,
                'anios_vida_util' => $grupo->first()->tipo?->anios_vida_util ?: null,
                'en_servicio'     => $grupo->count(),
                'cumplida'        => $grupo->filter(fn ($b) => $situacion($b) === 'cumplida')->count(),
                'proxima'         => $grupo->filter(fn ($b) => $situacion($b) === 'proxima')->count(),
                'sin_fecha'       => $grupo->filter(fn ($b) => $situacion($b) === 'sin_fecha')->count(),
            ])
            ->sortBy('tipo')->values();

        $equipos = $bienes->filter(fn ($b) => in_array($situacion($b), ['cumplida', 'proxima'], true))
            ->sortBy(fn (BienInformatico $b) => $b->fecha_fin_vida_util->toDateString() . '|' . $b->codigo_institucional)
            ->map(fn (BienInformatico $b) => [
                'id'                   => $b->id,
                'codigo_institucional' => $b->codigo_institucional,
                'tipo'                 => $b->tipo?->nombre,
                'marca'                => $b->marca?->nombre,
                'modelo'               => $b->modelo,
                'fecha_compra'         => $b->fecha_compra?->toDateString(),
                'anios'                => $b->fecha_compra ? (int) floor($b->fecha_compra->diffInYears($hoy)) : null,
                'fin_vida_util'        => $b->fecha_fin_vida_util->toDateString(),
                'situacion'            => $situacion($b),
                'estado'               => $b->estado_operativo->etiqueta(),
                'condicion'            => $b->condicion_fisica?->etiqueta(),
                'correctivos'          => $b->correctivos,
                'custodio'             => $b->asignacionVigente?->servidor?->nombre_completo,
                'unidad'               => ($b->asignacionVigente ? $b->asignacionVigente->servidor?->unidadAdministrativa : $b->unidadAdministrativa)?->nombre,
            ])
            ->values();

        return [
            'corte'   => $hoy->toDateString(),
            'resumen' => $resumen->all(),
            'equipos' => $equipos->all(),
            'totales' => [
                'en_servicio' => $bienes->count(),
                'cumplida'    => $resumen->sum('cumplida'),
                'proxima'     => $resumen->sum('proxima'),
                'sin_fecha'   => $resumen->sum('sin_fecha'),
            ],
        ];
    }
}
