<?php

namespace App\Services\InventarioTi;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Contracts\InventarioTi\MovimientoConsumibleServiceInterface;
use App\Contracts\InventarioTi\SolicitudBodegaServiceInterface;
use App\Enums\DocumentoIngresoConsumible;
use App\Enums\EstadoBien;
use App\Exceptions\ReglaNegocioException;
use App\Helpers\NumeroALetras;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\Ticket;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\SolicitudBodega;
use App\Models\InventarioTi\SolicitudBodegaItem;
use App\Models\User;
use App\Services\Estructura\FirmanteOrganigramaService;
use App\Services\Helpdesk\TicketEstadoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La «Solicitud de materiales informáticos a bodega» (fase 5.4c, P3): el pedido
 * que TIC le hace a Gestión Administrativa, con el formato del Excel de hoy.
 *
 * - Sale de un ticket «Consumibles» (a nombre de su solicitante y con su
 *   equipo) o, mientras no arranque la mesa de ayuda, del módulo de
 *   consumibles. Número correlativo por año.
 * - Firman el jefe de la unidad del servidor, el de TIC y el de Gestión
 *   Administrativa, todos del organigrama, sellados al generarla; quien
 *   solicita y quien entrega.
 * - Cuando Bodega despacha, TIC anota lo despachado: esa es la entrega, ligada
 *   al equipo y a la unidad, y cierra el ticket. Lo que guarda TIC entra antes a
 *   su saldo con la solicitud por documento y sale en la misma entrega.
 */
final class SolicitudBodegaService implements SolicitudBodegaServiceInterface
{
    private const RELACIONES = [
        'items.consumible:id,codigo_bodega,descripcion,tipo,color,presentacion,guarda_tic',
        'ticket:id,codigo_ticket,estado',
        'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula',
        'unidad:id,codigo,nombre',
        'bien:id,codigo_institucional,codigo_anterior,tipo_bien_id,marca_id,modelo', 'bien.tipo:id,nombre', 'bien.marca:id,nombre',
        'creadaPor:id,usuario_ti,email,servidor_id', 'creadaPor.servidor:id,nombre,apellido',
        'despachadaPor:id,usuario_ti,email,servidor_id', 'despachadaPor.servidor:id,nombre,apellido',
        'anuladaPor:id,usuario_ti,email,servidor_id', 'anuladaPor.servidor:id,nombre,apellido',
    ];

    public function __construct(
        private readonly MovimientoConsumibleServiceInterface $movimientos,
        private readonly FirmanteOrganigramaService $firmantes,
        private readonly TicketEstadoService $tickets,
    ) {}

    public function listar(array $filtros): LengthAwarePaginator
    {
        return SolicitudBodega::with(self::RELACIONES)
            ->when($filtros['estado'] ?? null, fn (Builder $q, $e) => $q->where('estado', $e))
            ->when($filtros['ticket_id'] ?? null, fn (Builder $q, $t) => $q->where('ticket_id', $t))
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->where(fn (Builder $w) => $w
                ->whereHas('servidor', fn (Builder $sv) => $sv->whereRaw("concat_ws(' ', nombre, segundo_nombre, apellido, segundo_apellido) ilike ?", ["%{$s}%"]))
                ->orWhereHas('bien', fn (Builder $b) => $b->where('codigo_institucional', 'ilike', "%{$s}%"))
                ->when(preg_match('/(\d{4})\D+(\d+)$/', $s, $m) === 1, fn (Builder $c) => $c->orWhere(fn (Builder $n) => $n
                    ->where('anio', (int) $m[1])->where('numero', (int) $m[2])))))
            ->orderByDesc('anio')->orderByDesc('numero')
            ->paginate(min(max((int) ($filtros['per_page'] ?? 15), 1), 100));
    }

    public function crear(array $datos, User $user): SolicitudBodega
    {
        $ticket = empty($datos['ticket_id']) ? null : Ticket::with('categoria')->findOrFail($datos['ticket_id']);
        if ($ticket) {
            $this->exigirTicketDeConsumibles($ticket);
        }

        $servidor = Servidor::findOrFail($ticket?->solicitante_id ?? $datos['servidor_id']);
        $bienId = $datos['bien_informatico_id'] ?? $ticket?->bien_informatico_id;
        $bien = $bienId ? BienInformatico::with('marca:id,nombre')->findOrFail($bienId) : null;
        if ($bien?->estado_operativo === EstadoBien::DADO_DE_BAJA) {
            throw new ReglaNegocioException("El equipo {$bien->codigo_institucional} está dado de baja: ya no se le piden consumibles.");
        }
        $items = $this->items($datos['items'], $bien);
        $fecha = Carbon::parse($datos['fecha'] ?? today());

        return DB::transaction(function () use ($datos, $user, $ticket, $servidor, $bien, $items, $fecha) {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["solicitud_bodega_{$fecha->year}"]);
            $numero = (int) SolicitudBodega::where('anio', $fecha->year)->max('numero') + 1;

            $solicitud = SolicitudBodega::create([
                'anio'                     => $fecha->year,
                'numero'                   => $numero,
                'fecha'                    => $fecha->toDateString(),
                'ticket_id'                => $ticket?->id,
                'servidor_id'              => $servidor->id,
                'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
                'bien_informatico_id'      => $bien?->id,
                'motivo'                   => trim($datos['motivo']),
                'estado'                   => SolicitudBodega::PENDIENTE,
                'firmantes'                => $this->sellarFirmantes($servidor, $fecha),
                'created_by'               => $user->id,
            ]);
            foreach ($items as $consumibleId => $cantidad) {
                $solicitud->items()->create(['consumible_id' => $consumibleId, 'cantidad_solicitada' => $cantidad]);
            }

            return $solicitud->load(self::RELACIONES);
        });
    }

    public function despachar(SolicitudBodega $solicitud, array $datos, User $user): SolicitudBodega
    {
        return DB::transaction(function () use ($solicitud, $datos, $user) {
            $s = SolicitudBodega::lockForUpdate()->findOrFail($solicitud->id);
            if ($s->estado !== SolicitudBodega::PENDIENTE) {
                throw new ReglaNegocioException("La solicitud {$s->codigo()} ya está {$s->estado}.");
            }
            $fecha = Carbon::parse($datos['fecha']);
            if ($fecha->lt($s->fecha)) {
                throw new ReglaNegocioException('Bodega no despacha antes de la solicitud (' . $s->fecha->format('d/m/Y') . ').');
            }

            $items = $s->items()->with('consumible')->get()->keyBy('id');
            $despachado = collect($datos['items'])->mapWithKeys(fn (array $i) => [(int) $i['item_id'] => (int) $i['cantidad_despachada']]);
            $ajenos = $despachado->keys()->diff($items->keys());
            if ($ajenos->isNotEmpty()) {
                throw new ReglaNegocioException('Algunos renglones no son de esta solicitud.');
            }
            foreach ($despachado as $itemId => $cantidad) {
                if ($cantidad > $items[$itemId]->cantidad_solicitada) {
                    throw new ReglaNegocioException("De «{$items[$itemId]->consumible->descripcion}» se pidieron {$items[$itemId]->cantidad_solicitada}: no se despacha más de lo pedido.");
                }
            }
            $entregados = $items->filter(fn (SolicitudBodegaItem $i) => ($despachado[$i->id] ?? 0) > 0);
            if ($entregados->isEmpty()) {
                throw new ReglaNegocioException('Bodega no despachó nada: anote al menos una cantidad, o anule la solicitud.');
            }

            $renglones = fn (Collection $de) => $de->map(fn (SolicitudBodegaItem $i) => ['consumible_id' => $i->consumible_id, 'cantidad' => $despachado[$i->id]])->values()->all();
            $deTic = $entregados->filter(fn (SolicitudBodegaItem $i) => $i->consumible->guarda_tic);
            // Lo que guarda TIC entra a su saldo con la solicitud por documento y sale en la entrega.
            if ($deTic->isNotEmpty()) {
                $this->movimientos->registrarIngreso([
                    'fecha'               => $fecha->toDateString(),
                    'tipo_documento'      => DocumentoIngresoConsumible::SOLICITUD_BODEGA->value,
                    'numero_documento'    => $s->codigo(),
                    'solicitud_bodega_id' => $s->id,
                    'observacion'         => 'Despachado por Bodega para la entrega de la misma solicitud.',
                    'items'               => $renglones($deTic),
                ], $user);
            }
            $this->movimientos->registrarEntrega([
                'fecha'               => $fecha->toDateString(),
                'bien_informatico_id' => $s->bien_informatico_id,
                'servidor_retira_id'  => $s->servidor_id,
                'ticket_id'           => $s->ticket_id,
                'solicitud_bodega_id' => $s->id,
                'observacion'         => "Solicitud a bodega {$s->codigo()}.",
                'items'               => $renglones($entregados),
            ], $user);

            foreach ($items as $item) {
                $item->update(['cantidad_despachada' => $despachado[$item->id] ?? 0]);
            }
            $s->update(['estado' => SolicitudBodega::DESPACHADA, 'fecha_despacho' => $fecha->toDateString(), 'despachada_por' => $user->id]);

            if ($s->ticket_id) {
                $lista = $entregados->map(fn (SolicitudBodegaItem $i) => "{$despachado[$i->id]} × {$i->consumible->descripcion}")->join('; ');
                $this->tickets->cerrarConEntrega(Ticket::findOrFail($s->ticket_id), $user, "Bodega despachó la solicitud {$s->codigo()}: {$lista}.");
            }

            return $s->load(self::RELACIONES);
        });
    }

    public function anular(SolicitudBodega $solicitud, string $motivo, User $user): SolicitudBodega
    {
        if ($solicitud->estado !== SolicitudBodega::PENDIENTE) {
            throw new ReglaNegocioException($solicitud->estado === SolicitudBodega::ANULADA
                ? 'Esa solicitud ya está anulada.'
                : 'Ya se despachó: para corregirla, anule sus movimientos en el kárdex de cada consumible.');
        }
        $solicitud->update([
            'estado' => SolicitudBodega::ANULADA, 'anulada_en' => now(), 'anulada_por' => $user->id, 'motivo_anulacion' => trim($motivo),
        ]);

        return $solicitud->load(self::RELACIONES);
    }

    public function pdf(SolicitudBodega $solicitud): array
    {
        $s = $solicitud->load(self::RELACIONES);
        $renglones = $s->items->values()->map(fn (SolicitudBodegaItem $i, int $n) => [
            'item'                => $n + 1,
            'codigo'              => $i->consumible->codigo_bodega,
            'descripcion'         => $i->consumible->descripcion,
            'solicitada'          => $i->cantidad_solicitada,
            'solicitada_letras'   => NumeroALetras::cantidad($i->cantidad_solicitada),
            'despachada'          => $i->cantidad_despachada,
            'despachada_letras'   => $i->cantidad_despachada === null ? null : NumeroALetras::cantidad($i->cantidad_despachada),
        ])->all();

        $pdf = Pdf::loadView('pdf.inventario.solicitud-bodega', [
            'solicitud' => $s,
            'codigo'    => $s->codigo(),
            'renglones' => $renglones,
            // Siempre al menos doce filas: se completan a mano si hace falta.
            'vacias'    => max(0, 12 - count($renglones)),
            'firmantes' => $s->firmantes,
            ...$this->procesoYSubproceso($s->unidad_administrativa_id),
            ...app(IdentidadInstitucionalServiceInterface::class)->paraDocumento($s->created_at),
        ])->setPaper('a4', 'landscape');

        return ['content' => $pdf->output(), 'filename' => strtolower($s->codigo()) . '.pdf'];
    }

    public function presentar(SolicitudBodega $s): array
    {
        $b = $s->bien;

        return [
            'id'              => $s->id,
            'codigo'          => $s->codigo(),
            'fecha'           => $s->fecha->toDateString(),
            'estado'          => $s->estado,
            'ticket_id'       => $s->ticket_id,
            'ticket'          => $s->ticket?->codigo_ticket,
            'servidor_id'     => $s->servidor_id,
            'servidor'        => $s->servidor?->nombre_completo,
            'unidad'          => $s->unidad?->nombre,
            'equipo'          => $b ? [
                'id'                   => $b->id,
                'codigo_institucional' => $b->codigo_institucional,
                'codigo_anterior'      => $b->codigo_anterior,
                'descripcion'          => trim(implode(' ', array_filter([$b->tipo?->nombre, $b->marca?->nombre, $b->modelo]))),
            ] : null,
            'motivo'          => $s->motivo,
            'firmantes'       => $s->firmantes,
            'items'           => $s->items->map(fn (SolicitudBodegaItem $i) => [
                'id'                  => $i->id,
                'consumible_id'       => $i->consumible_id,
                'codigo_bodega'       => $i->consumible?->codigo_bodega,
                'descripcion'         => $i->consumible?->descripcion,
                'guarda_tic'          => (bool) $i->consumible?->guarda_tic,
                'cantidad_solicitada' => $i->cantidad_solicitada,
                'cantidad_despachada' => $i->cantidad_despachada,
            ])->all(),
            'creada_por'       => $s->creadaPor?->nombre_completo,
            'creada_en'        => $s->created_at?->toIso8601String(),
            'fecha_despacho'   => $s->fecha_despacho?->toDateString(),
            'despachada_por'   => $s->despachadaPor?->nombre_completo,
            'anulada_en'       => $s->anulada_en?->toIso8601String(),
            'anulada_por'      => $s->anuladaPor?->nombre_completo,
            'motivo_anulacion' => $s->motivo_anulacion,
        ];
    }

    /**
     * «Proceso» y «Subproceso» del formulario: la dirección y, si el servidor
     * está en una unidad de ella, esa unidad.
     *
     * @return array{proceso: string|null, subproceso: string|null}
     */
    private function procesoYSubproceso(?int $unidadId): array
    {
        $cadena = [];
        $unidad = $unidadId ? UnidadAdministrativa::find($unidadId, ['id', 'nombre', 'unidad_padre_id']) : null;
        while ($unidad && count($cadena) < 10) {
            $cadena[] = $unidad;
            $unidad = $unidad->unidad_padre_id ? UnidadAdministrativa::find($unidad->unidad_padre_id, ['id', 'nombre', 'unidad_padre_id']) : null;
        }
        // La raíz es la institución: la dirección es la que cuelga de ella.
        if (count($cadena) > 1) {
            array_pop($cadena);
        }
        $direccion = end($cadena) ?: null;
        $propia = $cadena[0] ?? null;

        return [
            'proceso'    => $direccion?->nombre ?? $propia?->nombre,
            'subproceso' => $propia && $direccion && $propia->id !== $direccion->id ? $propia->nombre : null,
        ];
    }

    private function exigirTicketDeConsumibles(Ticket $ticket): void
    {
        if (! $ticket->categoria?->es_consumibles) {
            throw new ReglaNegocioException("El ticket {$ticket->codigo_ticket} no es de la categoría «Consumibles».");
        }
        if ($ticket->estado->final()) {
            throw new ReglaNegocioException("El ticket {$ticket->codigo_ticket} está " . mb_strtolower($ticket->estado->etiqueta()) . '.');
        }
        $pendiente = SolicitudBodega::where('ticket_id', $ticket->id)->where('estado', SolicitudBodega::PENDIENTE)->first();
        if ($pendiente) {
            throw new ReglaNegocioException("El ticket ya tiene la solicitud {$pendiente->codigo()} pendiente: despáchela o anúlela antes de pedir otra.");
        }
    }

    /**
     * Los renglones, uno por consumible. Lo de impresión va a un equipo de un
     * modelo compatible, como su entrega.
     *
     * @param  list<array{consumible_id: int, cantidad: int}>  $items
     * @return Collection<int, int>  cantidad por id de consumible
     */
    private function items(array $items, ?BienInformatico $bien): Collection
    {
        $porId = collect($items)->groupBy(fn (array $i) => (int) $i['consumible_id']);
        if ($porId->count() !== count($items)) {
            throw new ReglaNegocioException('Cada consumible va una sola vez: sume las cantidades en un renglón.');
        }

        $consumibles = Consumible::whereKey($porId->keys())->get()->keyBy('id');
        foreach ($consumibles as $c) {
            if (! $c->activo) {
                throw new ReglaNegocioException("«{$c->descripcion}» está desactivado.");
            }
            if ($c->tipo->deImpresion() && $bien === null) {
                throw new ReglaNegocioException("«{$c->descripcion}» es de impresión: la solicitud va con el equipo que lo usa.");
            }
            if ($c->tipo->deImpresion() && ! $c->sirveA($bien)) {
                throw new ReglaNegocioException("«{$c->descripcion}» no está marcado como compatible con el equipo {$bien->codigo_institucional}. Agregue el modelo a sus compatibles.");
            }
        }

        return $porId->map(fn (Collection $g) => (int) $g->first()['cantidad']);
    }

    /**
     * Los tres jefes del organigrama a la fecha de la solicitud, con su cargo.
     * Un puesto vacante sale con el cargo y sin nombre: se firma igual a mano.
     *
     * @return array<string, array{nombre: string|null, cargo: string|null}>
     */
    private function sellarFirmantes(Servidor $servidor, Carbon $fecha): array
    {
        $jefe = function (?int $unidadId, string $cargo) use ($fecha): array {
            $f = $this->firmantes->jefeDeUnidadEn($unidadId, $fecha->toDateString(), $cargo);

            return ['nombre' => $f['servidor']?->nombre_completo, 'cargo' => $f['cargo']];
        };
        $porCodigo = fn (string $codigo) => UnidadAdministrativa::where('codigo', $codigo)->value('id');

        return [
            'area'           => $jefe($servidor->unidad_administrativa_id, 'Director del área'),
            'tic'            => $jefe($porCodigo((string) config('helpdesk.unidad_tic_codigo')), 'Director de TIC'),
            'administrativo' => $jefe($porCodigo((string) config('inventario.unidad_bodega_codigo')), 'Director Administrativo'),
            'solicitante'    => ['nombre' => $servidor->nombre_completo, 'cargo' => null],
        ];
    }
}
