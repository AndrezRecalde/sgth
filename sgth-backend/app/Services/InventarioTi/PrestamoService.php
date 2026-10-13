<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\PrestamoServiceInterface;
use App\Contracts\InventarioTi\ReglasPrestamoServiceInterface;
use App\Enums\EstadoPrestamo;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\InventarioTi\PrestamoItem;
use App\Services\InventarioTi\Prestamos\EquiposDelPrestamo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Pedir y registrar préstamos de equipos (docs/diseno-subsistema-tic.md, 4.6;
 * fase 2.1). El trámite —aprobar, entregar, recibir— está en
 * `PrestamoEstadoService`.
 *
 * - El servidor elige los equipos concretos (D13) y el horario. La solicitud
 *   no ocupa el horario: dos personas pueden pedir el mismo proyector a la
 *   misma hora, pero solo una queda aprobada.
 * - El préstamo directo, en el mostrador, se registra y se entrega de una vez.
 */
final class PrestamoService implements PrestamoServiceInterface
{
    public const RELACIONES = [
        'servidor:id,nombre,apellido,cedula', 'unidadAdministrativa:id,nombre,acronimo',
        'items.bien:id,codigo_institucional,modelo,tipo_bien_id,marca_id',
        'items.bien.tipo:id,nombre', 'items.bien.marca:id,nombre',
    ];

    private const RESPONSABLES = [
        'registradoPor', 'aprobadoPor', 'rechazadoPor', 'canceladoPor', 'entregadoPor', 'recibidoPor',
    ];

    public function __construct(
        private readonly ReglasPrestamoServiceInterface $reglas,
        private readonly EquiposDelPrestamo $equipos,
    ) {}

    public function listar(array $filtros): LengthAwarePaginator
    {
        $vista = $filtros['vista'] ?? null;

        $query = PrestamoEquipo::with(self::RELACIONES)
            ->when($vista === 'por_aprobar', fn (Builder $q) => $q->where('estado', EstadoPrestamo::SOLICITADO))
            ->when($vista === 'por_entregar_hoy', fn (Builder $q) => $q->where('estado', EstadoPrestamo::RESERVADO)
                ->where('inicio_previsto', '<', now()->endOfDay()))
            ->when($vista === 'reservados', fn (Builder $q) => $q->where('estado', EstadoPrestamo::RESERVADO))
            ->when($vista === 'en_curso', fn (Builder $q) => $q->where('estado', EstadoPrestamo::EN_CURSO))
            ->when($vista === 'vencidos', fn (Builder $q) => $q->vencidos())
            ->when($vista === 'historial', fn (Builder $q) => $q->whereNotIn('estado', [
                EstadoPrestamo::SOLICITADO, EstadoPrestamo::RESERVADO, EstadoPrestamo::EN_CURSO,
            ]))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $e) => $q->where('estado', $e))
            ->when($filtros['servidor_id'] ?? null, fn (Builder $q, $id) => $q->where('servidor_id', $id))
            ->when($filtros['bien_informatico_id'] ?? null, fn (Builder $q, $id) => $q
                ->whereHas('items', fn (Builder $i) => $i->where('bien_informatico_id', $id)))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->where('fin_previsto', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->where('inicio_previsto', '<=', Carbon::parse($h)->endOfDay()))
            ->when(trim((string) ($filtros['search'] ?? '')), function (Builder $q, string $s) {
                $termino = "%{$s}%";
                $q->where(fn (Builder $w) => $w->where('folio', 'ilike', $termino)
                    ->orWhereHas('servidor', fn (Builder $sv) => $sv->where('cedula', 'ilike', $termino)
                        ->orWhereRaw("concat(apellido, ' ', nombre) ilike ?", [$termino])
                        ->orWhereRaw("concat(nombre, ' ', apellido) ilike ?", [$termino]))
                    ->orWhereHas('items.bien', fn (Builder $b) => $b->where('codigo_institucional', 'ilike', $termino)));
            });

        // Lo pendiente, de lo más próximo a lo más lejano; el historial, al revés.
        $vista === 'historial'
            ? $query->orderByDesc('inicio_previsto')->orderByDesc('id')
            : $query->orderBy('inicio_previsto')->orderBy('id');

        return $query->paginate(min(max((int) ($filtros['per_page'] ?? 20), 1), 100));
    }

    public function deServidor(int $servidorId, array $filtros): LengthAwarePaginator
    {
        return PrestamoEquipo::with(self::RELACIONES)
            ->where('servidor_id', $servidorId)
            ->when($filtros['estado'] ?? null, fn (Builder $q, $e) => $q->where('estado', $e))
            ->orderByDesc('inicio_previsto')
            ->orderByDesc('id')
            ->paginate(min(max((int) ($filtros['per_page'] ?? 20), 1), 100));
    }

    public function obtener(int $id): PrestamoEquipo
    {
        $usuario = fn (string $r) => ["{$r}:id,usuario_ti,email,servidor_id", "{$r}.servidor:id,nombre,apellido"];

        return PrestamoEquipo::with([
            ...self::RELACIONES,
            ...collect(self::RESPONSABLES)->flatMap($usuario)->all(),
            'historial', ...$usuario('historial.usuario'),
        ])->findOrFail($id);
    }

    public function resumen(): array
    {
        return [
            'por_aprobar'      => PrestamoEquipo::where('estado', EstadoPrestamo::SOLICITADO)->count(),
            'reservados'       => PrestamoEquipo::where('estado', EstadoPrestamo::RESERVADO)->count(),
            'por_entregar_hoy' => PrestamoEquipo::where('estado', EstadoPrestamo::RESERVADO)->where('inicio_previsto', '<', now()->endOfDay())->count(),
            'en_curso'         => PrestamoEquipo::where('estado', EstadoPrestamo::EN_CURSO)->count(),
            'vencidos'         => PrestamoEquipo::vencidos()->count(),
        ];
    }

    /**
     * D17: el registro basta, y el acta se imprime cuando hace falta —préstamos
     * de varios días o fuera del edificio—. Solo existe desde la entrega.
     */
    public function generarActa(int $id): array
    {
        $prestamo = PrestamoEquipo::with([
            'servidor', 'unidadAdministrativa', 'items.bien.tipo', 'items.bien.marca',
            'entregadoPor.servidor', 'recibidoPor.servidor',
        ])->findOrFail($id);

        if (! $prestamo->entregado_en) {
            throw new ReglaNegocioException('El acta se imprime desde la entrega: este préstamo todavía no se entrega.');
        }

        $pdf = Pdf::loadView('pdf.inventario.acta-prestamo', [
            'prestamo' => $prestamo,
            'logo'     => public_path('images/logo-gadpe.png'),
        ])->setPaper('a4', 'portrait');

        return ['content' => $pdf->output(), 'filename' => "acta-prestamo-{$prestamo->folio}.pdf"];
    }

    public function solicitar(int $servidorId, array $datos): PrestamoEquipo
    {
        return DB::transaction(function () use ($servidorId, $datos) {
            $servidor = Servidor::findOrFail($servidorId);
            [$inicio, $fin, $ids] = $this->horarioYEquipos($datos);

            $this->reglas->validarServidor($servidor);
            $this->reglas->validarHorario($inicio, $fin);
            $this->equipos->prestables($ids);
            $this->equipos->exigirLibres($ids, $inicio, $fin);
            $this->exigirSinRepetir($servidor->id, $ids, $inicio, $fin);

            $prestamo = $this->crear($servidor, $datos, $inicio, $fin, EstadoPrestamo::SOLICITADO);
            $this->crearItems($prestamo, $ids);
            $this->equipos->anotar($prestamo, null);

            return $this->obtener($prestamo->id);
        });
    }

    public function registrarDirecto(array $datos): PrestamoEquipo
    {
        return DB::transaction(function () use ($datos) {
            $servidor = Servidor::findOrFail($datos['servidor_id']);
            $inicio = now()->startOfMinute();
            $fin = Carbon::parse($datos['fin_previsto']);
            $items = collect($datos['items']);
            $ids = $items->pluck('bien_informatico_id')->map(fn ($id) => (int) $id)->all();

            $this->reglas->validarServidor($servidor);
            $this->reglas->validarHorario($inicio, $fin, directo: true);
            $this->equipos->prestables($ids);
            $this->equipos->exigirEnTic($ids);
            $this->equipos->exigirLibres($ids, $inicio, $fin);

            $prestamo = $this->crear($servidor, $datos, $inicio, $fin, EstadoPrestamo::EN_CURSO, [
                'directo'       => true,
                'aprobado_por'  => auth()->id(),
                'aprobado_en'   => now(),
                'entregado_por' => auth()->id(),
                'entregado_en'  => now(),
            ]);
            $this->crearItems($prestamo, $ids, $items->keyBy('bien_informatico_id')->all());
            $this->equipos->sincronizarBloqueo($prestamo);
            $this->equipos->anotar($prestamo, null, 'Préstamo directo en el mostrador.');

            return $this->obtener($prestamo->id);
        });
    }

    /** @return array{0: Carbon, 1: Carbon, 2: list<int>} */
    private function horarioYEquipos(array $datos): array
    {
        return [
            Carbon::parse($datos['inicio_previsto'])->startOfMinute(),
            Carbon::parse($datos['fin_previsto'])->startOfMinute(),
            array_values(array_unique(array_map('intval', $datos['bienes']))),
        ];
    }

    /** Que el mismo servidor no pida dos veces el mismo equipo para el mismo horario. */
    private function exigirSinRepetir(int $servidorId, array $ids, Carbon $inicio, Carbon $fin): void
    {
        $repetido = PrestamoItem::whereIn('bien_informatico_id', $ids)
            ->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio)
            ->whereHas('prestamo', fn (Builder $q) => $q->where('servidor_id', $servidorId)
                ->whereIn('estado', [EstadoPrestamo::SOLICITADO, EstadoPrestamo::RESERVADO, EstadoPrestamo::EN_CURSO]))
            ->with('prestamo:id,folio')
            ->first();

        if ($repetido) {
            throw new ReglaNegocioException("Ya pidió ese equipo para ese horario en el préstamo {$repetido->prestamo->folio}.");
        }
    }

    private function crear(Servidor $servidor, array $datos, Carbon $inicio, Carbon $fin, EstadoPrestamo $estado, array $extra = []): PrestamoEquipo
    {
        return PrestamoEquipo::create([
            'folio'                    => $this->siguienteFolio(),
            'servidor_id'              => $servidor->id,
            'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
            'estado'                   => $estado,
            'motivo'                   => $datos['motivo'],
            'lugar_uso'                => $datos['lugar_uso'],
            'fuera_del_edificio'       => (bool) ($datos['fuera_del_edificio'] ?? false),
            'inicio_previsto'          => $inicio,
            'fin_previsto'             => $fin,
            'registrado_por'           => auth()->id(),
            'observaciones'            => $datos['observaciones'] ?? null,
            ...$extra,
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $entrega  por equipo: accesorios y condición, en el directo */
    private function crearItems(PrestamoEquipo $prestamo, array $ids, array $entrega = []): void
    {
        foreach ($ids as $id) {
            PrestamoItem::create([
                'prestamo_equipo_id'  => $prestamo->id,
                'bien_informatico_id' => $id,
                'inicio'              => $prestamo->inicio_previsto,
                'fin'                 => $prestamo->fin_previsto,
                'accesorios'          => $entrega[$id]['accesorios'] ?? null,
                'condicion_entrega'   => $entrega[$id]['condicion_entrega'] ?? null,
            ]);
        }
    }

    /** PRE-AAAA-NNNNN, con el candado de aviso de los demás folios. */
    private function siguienteFolio(): string
    {
        $anio = (int) now()->format('Y');
        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["prestamo_folio_{$anio}"]);

        $ultimo = PrestamoEquipo::where('folio', 'like', "PRE-{$anio}-%")->max('folio');
        $secuencial = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('PRE-%d-%05d', $anio, $secuencial);
    }
}
