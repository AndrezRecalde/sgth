<?php

namespace App\Services\Viatico;

use App\Contracts\Viatico\ViaticoServiceInterface;
use App\Enums\EstadoViatico;
use App\Models\Asistencia\FeriadoInstitucional;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\Viatico;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginador;

/**
 * La bandeja de Financiero: qué espera su intervención, cuánto dinero hay en
 * cada etapa y qué liquidaciones están por vencer.
 *
 * Hasta ahora Financiero trabajaba sobre el mismo listado que el servidor, con
 * un filtro por estado, y sin forma de ver qué liquidaciones estaban fuera de
 * plazo. Las etapas no son los estados: agrupan por lo que hay que hacer.
 *
 * Decidido con el usuario: página propia en el portal, con el plazo de
 * liquidación, los montos por etapa y filtros por unidad, fechas y búsqueda.
 */
final class BandejaViaticoService
{
    /**
     * Qué estados caen en cada pestaña. «Por entregar anticipo» y «listos para
     * la comisión» parten del mismo estado `aprobado` y los separa la
     * modalidad del anticipo.
     */
    public const ETAPAS = [
        'por_aprobar',
        'por_anticipo',
        'por_iniciar',
        'en_comision',
        'por_liquidar',
        'por_revisar',
        'cerrados',
    ];

    public function __construct(
        private readonly ViaticoServiceInterface $viaticos,
    ) {}

    /**
     * Contadores por etapa, montos y las unidades con viáticos, para el
     * filtro. Los filtros se aplican a todo, así el contador de una pestaña
     * coincide con lo que muestra al abrirla.
     *
     * @param array{unidad_id?: int|string|null, desde?: string|null, hasta?: string|null, search?: string|null} $filtros
     * @return array<string, mixed>
     */
    public function resumen(array $filtros): array
    {
        $conteos = [];
        foreach (self::ETAPAS as $etapa) {
            $conteos[$etapa] = $this->consulta($etapa, $filtros)->count();
        }

        $conteos['vuelos'] = AutorizacionVuelo::where('estado', 'pendiente')
            ->whereHas('viatico', fn (Builder $q) => $this->filtrar($q, $filtros))
            ->count();

        $vencidas = $this->consulta('por_liquidar', $filtros)->get()
            ->filter(fn (Viatico $v) => now()->gt($this->viaticos->fechaLimiteLiquidacion($v)))
            ->count();

        $activos = [
            EstadoViatico::APROBADO, EstadoViatico::CON_ANTICIPO, EstadoViatico::EN_COMISION,
            EstadoViatico::PENDIENTE_LIQUIDACION, EstadoViatico::LIQUIDADO,
        ];

        $base = fn () => $this->filtrar(Viatico::query(), $filtros);

        return [
            'conteos'  => $conteos,
            'vencidas' => $vencidas,
            'montos'   => [
                // Lo que la institución se comprometió a pagar y aún no cerró.
                'comprometido'        => round((float) $base()->whereIn('estado', $activos)->sum('monto_calculado'), 2),
                // Dinero que ya salió como anticipo en viáticos sin cerrar.
                'anticipos_entregados' => round((float) $base()->whereIn('estado', $activos)->sum('monto_anticipo'), 2),
                // Liquidado por el servidor, esperando la revisión de Financiero.
                'por_contabilizar'    => round((float) $base()->where('estado', EstadoViatico::LIQUIDADO)->sum('monto_calculado'), 2),
            ],
            'unidades' => UnidadAdministrativa::whereIn(
                'id',
                Viatico::query()->join('servidores', 'servidores.id', '=', 'viaticos.servidor_id')
                    ->select('servidores.unidad_administrativa_id')
            )->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    /**
     * Los viáticos de una etapa, paginados. En «por liquidar» cada fila lleva
     * su plazo, y `vencidas` deja solo las que ya lo pasaron.
     *
     * @param array{unidad_id?: int|string|null, desde?: string|null, hasta?: string|null, search?: string|null, vencidas?: bool} $filtros
     */
    public function listar(string $etapa, array $filtros, int $porPagina, int $pagina): LengthAwarePaginator
    {
        $consulta = $this->consulta($etapa, $filtros)
            ->with(['servidor:id,nombre,apellido,cedula,unidad_administrativa_id', 'servidor.unidadAdministrativa:id,nombre']);

        if ($etapa !== 'por_liquidar') {
            return $consulta->orderBy($etapa === 'cerrados' ? 'updated_at' : 'datetime_salida', $etapa === 'cerrados' ? 'desc' : 'asc')
                ->orderBy('id')
                ->paginate($porPagina, ['*'], 'page', $pagina);
        }

        // El plazo depende de los feriados, así que se calcula en PHP: la
        // etapa suele tener pocas filas, y ordenar por lo que vence antes pide
        // tenerlas todas.
        $filas = $consulta->get()
            ->each(fn (Viatico $v) => $v->setAttribute('plazo', $this->plazo($v)))
            ->when(! empty($filtros['vencidas']), fn ($c) => $c->filter(fn (Viatico $v) => $v->plazo['vencida']))
            ->sortBy(fn (Viatico $v) => $v->plazo['fecha_limite'])
            ->values();

        return new Paginador(
            $filas->forPage($pagina, $porPagina)->values(),
            $filas->count(),
            $porPagina,
            $pagina,
        );
    }

    /**
     * @return array{fecha_limite: string, dias_habiles_restantes: int, vencida: bool}
     */
    public function plazo(Viatico $viatico): array
    {
        $limite = $this->viaticos->fechaLimiteLiquidacion($viatico);
        $vencida = now()->gt($limite);

        // Días hábiles desde mañana hasta el día límite inclusive: el día
        // límite cuenta como uno que queda; hoy, no.
        $restantes = 0;
        for ($dia = Carbon::today()->addDay(); $dia->lte($limite->copy()->startOfDay()); $dia->addDay()) {
            if (! $dia->isWeekend() && ! FeriadoInstitucional::esFeriado($dia)->exists()) {
                $restantes++;
            }
        }

        return [
            'fecha_limite'           => $limite->toIso8601String(),
            'dias_habiles_restantes' => $vencida ? 0 : $restantes,
            'vencida'                => $vencida,
        ];
    }

    private function consulta(string $etapa, array $filtros): Builder
    {
        $consulta = $this->filtrar(Viatico::query(), $filtros);

        return match ($etapa) {
            'por_aprobar'  => $consulta->where('estado', EstadoViatico::SOLICITADO),
            'por_anticipo' => $consulta->where('estado', EstadoViatico::APROBADO)
                ->where('modalidad_anticipo', '!=', 'sin_anticipo'),
            'por_iniciar'  => $consulta->where(fn (Builder $q) => $q
                ->where('estado', EstadoViatico::CON_ANTICIPO)
                ->orWhere(fn (Builder $a) => $a->where('estado', EstadoViatico::APROBADO)
                    ->where('modalidad_anticipo', 'sin_anticipo'))),
            'en_comision'  => $consulta->where('estado', EstadoViatico::EN_COMISION),
            'por_liquidar' => $consulta->where('estado', EstadoViatico::PENDIENTE_LIQUIDACION),
            'por_revisar'  => $consulta->where('estado', EstadoViatico::LIQUIDADO),
            'cerrados'     => $consulta->whereIn('estado', [
                EstadoViatico::CONTABILIZADO, EstadoViatico::RECHAZADO, EstadoViatico::CANCELADO,
            ]),
        };
    }

    private function filtrar(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when($filtros['unidad_id'] ?? null, fn (Builder $q, $unidad) => $q->whereHas(
                'servidor', fn (Builder $s) => $s->where('unidad_administrativa_id', (int) $unidad)
            ))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $desde) => $q->whereDate('datetime_salida', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $hasta) => $q->whereDate('datetime_salida', '<=', $hasta))
            ->when(trim((string) ($filtros['search'] ?? '')), function (Builder $q, string $texto) {
                $patron = '%' . str_replace(['%', '_'], ['\%', '\_'], $texto) . '%';

                $q->where(fn (Builder $w) => $w
                    ->where('codigo_viatico', 'ilike', $patron)
                    ->orWhereHas('servidor', fn (Builder $s) => $s
                        ->where('cedula', 'ilike', $patron)
                        ->orWhereRaw("concat_ws(' ', nombre, apellido) ilike ?", [$patron])
                        ->orWhereRaw("concat_ws(' ', apellido, nombre) ilike ?", [$patron])));
            });
    }
}
