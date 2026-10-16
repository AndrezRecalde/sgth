<?php

namespace App\Services\Tic;

use App\Enums\EstadoBaja;
use App\Enums\EstadoBien;
use App\Enums\EstadoPrestamo;
use App\Enums\EstadoTicket;
use App\Enums\Permiso;
use App\Enums\TipoMovimientoConsumible;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\Ticket;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BajaBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\ContratoVigencia;
use App\Models\InventarioTi\CronogramaVigencia;
use App\Models\InventarioTi\MovimientoConsumible;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\User;
use App\Services\Helpdesk\Tickets\MetricasTickets;
use App\Services\InventarioTi\PrestamoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * El tablero de Inicio de Tecnologías (fase 4.1). Cada bloque sale solo con
 * su permiso: la mesa de ayuda a quien atiende tickets, el inventario a quien
 * lo consulta y los préstamos a quien los gestiona o consulta el inventario.
 * Un bloque sin permiso va en null y la pantalla no lo pinta.
 */
final class TableroTicService
{
    /** Las unidades que se nombran en el reparto; el resto se suma en «Otras». */
    private const UNIDADES_VISIBLES = 8;

    /** Días hacia adelante para avisar del fin de un contrato de vigencia. */
    private const DIAS_CONTRATO = 90;

    /** Días hacia adelante para las ventanas de mantenimiento del proveedor. */
    private const DIAS_VENTANA = 30;

    public function __construct(
        private readonly MetricasTickets $metricas,
        private readonly PrestamoService $prestamos,
    ) {}

    /** @return array{tickets: ?array<string, mixed>, equipos: ?array<string, mixed>, prestamos: ?array<string, mixed>, consumibles: ?array<string, mixed>} */
    public function para(User $user): array
    {
        $atiende = $user->canAny([Permiso::GESTIONAR_TICKETS->value, Permiso::VER_TICKETS_TODOS->value]);
        $inventario = $user->canAny([Permiso::VER_INVENTARIO_TI->value, Permiso::GESTIONAR_INVENTARIO_TI->value]);

        return [
            'tickets'   => $atiende ? $this->tickets($user) : null,
            'equipos'   => $inventario ? $this->equipos($user) : null,
            'prestamos' => $inventario || $user->can(Permiso::GESTIONAR_PRESTAMOS->value) ? $this->prestamos() : null,
            'consumibles' => $inventario ? $this->consumibles() : null,
        ];
    }

    /**
     * Lo que guarda TIC y bajó del mínimo (fase 5.4d): el aviso de stock va
     * solo aquí [P6]. Primero lo que más falta; y las unidades entregadas en
     * el mes, de TIC y de Bodega.
     *
     * @return array<string, mixed>
     */
    private function consumibles(): array
    {
        $bajos = Consumible::conSaldo()
            ->where('guarda_tic', true)->where('activo', true)->where('stock_minimo', '>', 0)
            ->get(['id', 'descripcion', 'stock_minimo', 'guarda_tic', 'activo'])
            ->map(fn (Consumible $c) => ['id' => $c->id, 'descripcion' => $c->descripcion, 'saldo' => (int) $c->saldo, 'stock_minimo' => $c->stock_minimo])
            ->filter(fn (array $c) => $c['saldo'] < $c['stock_minimo'])
            ->sortBy([fn (array $a, array $b) => ($a['saldo'] - $a['stock_minimo']) <=> ($b['saldo'] - $b['stock_minimo']), ['descripcion', 'asc']])
            ->values();

        return [
            'bajo_minimo'  => $bajos->count(),
            'lista'        => $bajos->take(8)->all(),
            'entregas_mes' => (int) -MovimientoConsumible::where('tipo', TipoMovimientoConsumible::ENTREGA)->whereNull('anulado_en')
                ->where('fecha', '>=', now()->startOfMonth()->toDateString())->sum('cantidad'),
        ];
    }

    /** @return array<string, mixed> */
    private function tickets(User $user): array
    {
        $visibles = fn () => Ticket::visiblesPara($user);
        $enCurso = fn () => $visibles()->whereIn('estado', EstadoTicket::enCurso());
        // Las cifras del mes: de toda la mesa para quien la ve entera; de su
        // cola para el técnico.
        $todos = $user->can(Permiso::VER_TICKETS_TODOS->value);
        $delMes = $todos ? Ticket::query() : Ticket::where('tecnico_id', $user->id);

        $porEstado = $enCurso()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return [
            'alcance'      => $todos ? 'todos' : 'propios',
            'por_estado'   => collect(EstadoTicket::enCurso())->mapWithKeys(fn (string $e) => [$e => (int) ($porEstado[$e] ?? 0)])->all(),
            'sin_asignar'  => $enCurso()->whereNull('tecnico_id')->count(),
            'mios'         => $enCurso()->where('tecnico_id', $user->id)->count(),
            'vencidos'     => $visibles()->vencidos()->count(),
            'por_vencer'   => $enCurso()->whereNull('pausado_desde')->whereNotNull('alerta_sla_en')->where('fecha_vencimiento_sla', '>=', now())->count(),
            'por_confirmar' => $visibles()->where('estado', EstadoTicket::RESUELTO)->count(),
            'mes'          => $this->metricas->delPeriodo($delMes, now()->startOfMonth(), now()),
            'por_categoria' => $this->porCategoria($enCurso()),
            'mi_cola'      => Ticket::where('tecnico_id', $user->id)->whereIn('estado', EstadoTicket::enCurso())
                ->orderByRaw('fecha_vencimiento_sla ASC NULLS LAST')->orderBy('id')->limit(6)->get()
                ->map(fn (Ticket $t) => [
                    'id' => $t->id, 'folio' => $t->codigo_ticket, 'asunto' => $t->asunto,
                    'estado' => $t->estado->value, 'prioridad' => $t->prioridad->value,
                    'sla' => $t->estadoSla(), 'vence_en' => $t->fecha_vencimiento_sla?->toIso8601String(),
                ])->all(),
        ];
    }

    /**
     * Lo que hay en curso por categoría, las seis con más tickets.
     *
     * @param  Builder<Ticket>  $enCurso
     * @return list<array{categoria: string, total: int}>
     */
    private function porCategoria(Builder $enCurso): array
    {
        $totales = $enCurso->selectRaw('categoria_id, count(*) as total')->groupBy('categoria_id')
            ->orderByDesc('total')->orderBy('categoria_id')->limit(6)->pluck('total', 'categoria_id');
        $nombres = CategoriaTicket::whereIn('id', $totales->keys()->filter())->pluck('nombre', 'id');

        return $totales->map(fn ($total, $id) => ['categoria' => $nombres[$id] ?? 'Sin categoría', 'total' => (int) $total])->values()->all();
    }

    /** @return array<string, mixed> */
    private function equipos(User $user): array
    {
        // Los equipos principales: un monitor o un teclado registrado como
        // componente se cuenta con su computador.
        $principales = fn () => BienInformatico::whereNull('bien_padre_id');
        $porEstado = $principales()->selectRaw('estado_operativo, count(*) as total')->groupBy('estado_operativo')->pluck('total', 'estado_operativo');
        $enServicio = fn () => $principales()->where('estado_operativo', '<>', EstadoBien::DADO_DE_BAJA->value);

        return [
            'en_servicio'        => $enServicio()->count(),
            'por_estado'         => collect(EstadoBien::cases())->mapWithKeys(fn (EstadoBien $e) => [$e->value => (int) ($porEstado[$e->value] ?? 0)])->all(),
            'por_unidad'         => $this->porUnidad(),
            'antiguedad'         => $this->antiguedad(),
            'vida_util_cumplida' => $enServicio()->whereNotNull('fecha_fin_vida_util')->whereDate('fecha_fin_vida_util', '<', today())->count(),
            'bajas_por_resolver' => $user->can(Permiso::DAR_BAJA_BIEN->value)
                ? BajaBien::where('estado', EstadoBaja::PROPUESTA)->whereNull('baja_principal_id')->count()
                : null,
            'contratos_por_vencer' => ContratoVigencia::withCount('bienes')
                ->whereBetween('fecha_fin', [today(), today()->addDays(self::DIAS_CONTRATO)])
                ->orderBy('fecha_fin')->orderBy('id')->get()
                ->map(fn (ContratoVigencia $c) => [
                    'id' => $c->id, 'proceso' => $c->proceso, 'proveedor' => $c->proveedor,
                    'fecha_fin' => $c->fecha_fin->toDateString(), 'dias' => (int) today()->diffInDays($c->fecha_fin),
                    'equipos' => $c->bienes_count,
                ])->all(),
            'ventanas_proximas' => CronogramaVigencia::with('contrato:id,proceso,proveedor')
                ->whereHas('contrato')
                ->whereDate('fecha_hasta', '>=', today())->whereDate('fecha_desde', '<=', today()->addDays(self::DIAS_VENTANA))
                ->orderBy('fecha_desde')->orderBy('id')->limit(5)->get()
                ->map(fn (CronogramaVigencia $v) => [
                    'id' => $v->id, 'contrato_id' => $v->contrato_vigencia_id, 'proceso' => $v->contrato?->proceso,
                    'proveedor' => $v->contrato?->proveedor, 'desde' => $v->fecha_desde->toDateString(),
                    'hasta' => $v->fecha_hasta->toDateString(), 'descripcion' => $v->descripcion,
                ])->all(),
        ];
    }

    /**
     * La unidad de un equipo es la de quien lo tiene; la del propio equipo
     * solo vale mientras nadie lo tenga (igual que el filtro del listado).
     *
     * @return list<array{unidad_id: ?int, unidad: string, total: int}>
     */
    private function porUnidad(): array
    {
        $filas = DB::table('bienes_informaticos as b')
            ->leftJoin('asignaciones_bien as a', fn ($j) => $j->on('a.bien_informatico_id', '=', 'b.id')
                ->where('a.estado', AsignacionBien::ESTADO_ACTIVA)->whereNull('a.deleted_at'))
            ->leftJoin('servidores as s', 's.id', '=', 'a.servidor_id')
            ->leftJoin('unidades_administrativas as u', 'u.id', '=', DB::raw('coalesce(s.unidad_administrativa_id, b.unidad_administrativa_id)'))
            ->whereNull('b.deleted_at')->whereNull('b.bien_padre_id')
            ->where('b.estado_operativo', '<>', EstadoBien::DADO_DE_BAJA->value)
            ->groupBy('u.id', 'u.nombre')
            ->selectRaw('u.id as unidad_id, u.nombre as unidad, count(*) as total')
            ->orderByDesc('total')->orderBy('u.nombre')
            ->get();

        $visibles = $filas->filter(fn ($f) => $f->unidad_id !== null)->take(self::UNIDADES_VISIBLES);
        $resto = $filas->filter(fn ($f) => $f->unidad_id !== null)->skip(self::UNIDADES_VISIBLES)->sum('total');
        $sinUnidad = $filas->firstWhere('unidad_id', null)?->total ?? 0;

        return $visibles->map(fn ($f) => ['unidad_id' => (int) $f->unidad_id, 'unidad' => $f->unidad, 'total' => (int) $f->total])
            ->when($resto > 0, fn ($c) => $c->push(['unidad_id' => null, 'unidad' => 'Otras unidades', 'total' => (int) $resto]))
            ->when($sinUnidad > 0, fn ($c) => $c->push(['unidad_id' => null, 'unidad' => 'Sin unidad', 'total' => (int) $sinUnidad]))
            ->values()->all();
    }

    /**
     * Los años desde la compra, en tramos. Sin fecha de compra van aparte: el
     * Excel de TIC no la tenía para todos.
     *
     * @return array<string, int>
     */
    private function antiguedad(): array
    {
        $tramos = ['menos_de_3' => 0, 'de_3_a_5' => 0, 'de_5_a_8' => 0, 'mas_de_8' => 0, 'sin_fecha' => 0];

        $filas = BienInformatico::whereNull('bien_padre_id')
            ->where('estado_operativo', '<>', EstadoBien::DADO_DE_BAJA->value)
            ->selectRaw("case
                when fecha_compra is null then 'sin_fecha'
                when fecha_compra > current_date - interval '3 years' then 'menos_de_3'
                when fecha_compra > current_date - interval '5 years' then 'de_3_a_5'
                when fecha_compra > current_date - interval '8 years' then 'de_5_a_8'
                else 'mas_de_8' end as tramo, count(*) as total")
            ->groupBy('tramo')
            ->pluck('total', 'tramo');

        return collect($tramos)->map(fn (int $cero, string $tramo) => (int) ($filas[$tramo] ?? $cero))->all();
    }

    /** @return array<string, mixed> */
    private function prestamos(): array
    {
        $fila = fn (PrestamoEquipo $p, string $campo) => [
            'id' => $p->id, 'folio' => $p->folio, 'servidor' => $p->servidor?->nombre_completo,
            'hora' => $p->{$campo}?->toIso8601String(),
        ];

        return [
            ...$this->prestamos->resumen(),
            'lista_vencidos' => PrestamoEquipo::vencidos()->with('servidor:id,nombre,apellido')
                ->orderBy('fin_previsto')->orderBy('id')->limit(5)->get()
                ->map(fn (PrestamoEquipo $p) => $fila($p, 'fin_previsto'))->all(),
            'entregas_hoy' => PrestamoEquipo::where('estado', EstadoPrestamo::RESERVADO)
                ->whereBetween('inicio_previsto', [today(), today()->endOfDay()])
                ->with('servidor:id,nombre,apellido')->orderBy('inicio_previsto')->orderBy('id')->limit(5)->get()
                ->map(fn (PrestamoEquipo $p) => $fila($p, 'inicio_previsto'))->all(),
        ];
    }
}
