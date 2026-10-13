<?php

namespace App\Services\Helpdesk;

use App\Contracts\Helpdesk\TicketEstadoServiceInterface;
use App\Contracts\Helpdesk\TicketServiceInterface;
use App\Enums\CanalTicket;
use App\Enums\EstadoTicket;
use App\Enums\Permiso;
use App\Enums\PrioridadTicket;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\ComentarioTicket;
use App\Models\Helpdesk\Ticket;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\User;
use App\Services\Helpdesk\Tickets\TicketCarga;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Abrir tickets y su conversación (docs/diseno-subsistema-tic.md, 4.2 y 5.3;
 * fase 3.1). El trámite está en `TicketEstadoService`.
 *
 * Lo que se arregló al rehacerlo: crear un ticket daba 500 (no llenaba el
 * solicitante ni el tipo), el folio era `TIC-` y un `uniqid()`, y un ticket de
 * hardware dejaba un mantenimiento «correctivo» registrado al abrirse, antes de
 * que nadie lo revisara. Ahora el mantenimiento se registra al resolverlo.
 */
final class TicketService implements TicketServiceInterface
{
    public function __construct(
        private readonly TicketEstadoServiceInterface $estados,
        private readonly TicketCarga $carga,
    ) {}

    public function listar(User $user, array $filtros): LengthAwarePaginator
    {
        $vista = $filtros['vista'] ?? null;

        return Ticket::visiblesPara($user)
            ->with(TicketCarga::LISTADO)
            // «Mis tickets» del Portal: los que abrió, aunque además atienda.
            ->when(! empty($filtros['propios']), fn (Builder $q) => $q->where('solicitante_id', $user->servidor_id ?? 0))
            ->when($vista === 'sin_asignar', fn (Builder $q) => $q->whereNull('tecnico_id')->whereIn('estado', EstadoTicket::enCurso()))
            ->when($vista === 'mios', fn (Builder $q) => $q->where('tecnico_id', $user->id)->whereIn('estado', EstadoTicket::enCurso()))
            ->when($vista === 'en_curso', fn (Builder $q) => $q->whereIn('estado', EstadoTicket::enCurso()))
            ->when($vista === 'resueltos', fn (Builder $q) => $q->where('estado', EstadoTicket::RESUELTO))
            ->when($vista === 'cerrados', fn (Builder $q) => $q->whereIn('estado', [EstadoTicket::CERRADO, EstadoTicket::ANULADO]))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $e) => $q->where('estado', $e))
            ->when($filtros['categoria_id'] ?? null, fn (Builder $q, $c) => $q->where('categoria_id', $c))
            ->when($filtros['prioridad'] ?? null, fn (Builder $q, $p) => $q->where('prioridad', $p))
            ->when($filtros['unidad_tic_id'] ?? null, fn (Builder $q, $u) => $q->where('unidad_tic_id', $u))
            ->when($filtros['tecnico_id'] ?? null, fn (Builder $q, $t) => $q->where('tecnico_id', $t))
            ->when(trim((string) ($filtros['search'] ?? '')), function (Builder $q, string $s) {
                $t = "%{$s}%";
                $q->where(fn (Builder $w) => $w->where('codigo_ticket', 'ilike', $t)->orWhere('asunto', 'ilike', $t)
                    ->orWhereHas('solicitante', fn (Builder $sv) => $sv->where('cedula', 'ilike', $t)
                        ->orWhereRaw("concat(apellido, ' ', nombre) ilike ?", [$t])
                        ->orWhereRaw("concat(nombre, ' ', apellido) ilike ?", [$t])));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max((int) ($filtros['per_page'] ?? 20), 1), 100));
    }

    public function obtener(int $id): Ticket
    {
        return $this->carga->cargar($id);
    }

    public function crear(User $user, array $datos, ?UploadedFile $archivo = null): Ticket
    {
        $porTic = ! empty($datos['servidor_id']);
        $solicitanteId = $porTic ? (int) $datos['servidor_id'] : $user->servidor_id;
        if (! $solicitanteId) {
            throw new ReglaNegocioException('Su usuario no está vinculado a un expediente: pida a TIC que registre el ticket a su nombre.');
        }

        $solicitante = Servidor::findOrFail($solicitanteId);
        $categoria = CategoriaTicket::where('activa', true)->findOrFail($datos['categoria_id']);
        if (! empty($datos['bien_informatico_id']) && ! $porTic) {
            $this->exigirEquipoPropio($solicitante->id, (int) $datos['bien_informatico_id']);
        }

        $ticket = DB::transaction(function () use ($user, $datos, $porTic, $solicitante, $categoria, $archivo) {
            $ticket = Ticket::create([
                'codigo_ticket'       => $this->siguienteFolio(),
                'solicitante_id'      => $solicitante->id,
                'tipo_ticket'         => $datos['tipo_ticket'] ?? 'incidente',
                'categoria_id'        => $categoria->id,
                'estado'              => EstadoTicket::ABIERTO,
                // La prioridad la pone TIC; quien abre desde el Portal no la elige.
                'prioridad'           => $porTic ? ($datos['prioridad'] ?? PrioridadTicket::MEDIA->value) : PrioridadTicket::MEDIA,
                'canal'               => $porTic ? CanalTicket::TIC : CanalTicket::PORTAL,
                'asunto'              => $datos['asunto'],
                'descripcion'         => $datos['descripcion'],
                'bien_informatico_id' => $datos['bien_informatico_id'] ?? null,
                'unidad_tic_id'       => $categoria->unidad_tic_id,
                'nivel'               => 1,
                'registrado_por'      => $user->id,
            ]);
            $this->carga->anotar($ticket, null, $porTic ? 'Registrado por TIC.' : null);

            if ($archivo) {
                $this->guardarComentario($ticket, $user, 'Archivo adjunto al abrir el ticket.', false, $archivo);
            }

            return $ticket;
        });

        return $this->obtener($ticket->id);
    }

    public function comentar(Ticket $ticket, User $user, string $texto, bool $interno, ?UploadedFile $archivo = null): ComentarioTicket
    {
        if ($ticket->estado->final()) {
            throw new ReglaNegocioException("El ticket {$ticket->codigo_ticket} está " . mb_strtolower($ticket->estado->etiqueta()) . ': ya no recibe mensajes.');
        }

        $comentario = DB::transaction(fn () => $this->guardarComentario($ticket, $user, $texto, $interno, $archivo));

        // La respuesta del solicitante devuelve el ticket a quien lo atiende.
        $esSolicitante = $user->servidor_id && (int) $user->servidor_id === (int) $ticket->solicitante_id;
        if (! $interno && $esSolicitante && $ticket->estado === EstadoTicket::PENDIENTE_USUARIO) {
            $this->estados->reanudar($ticket, $user);
        }

        return $comentario->load('user:id,usuario_ti,email,servidor_id', 'user.servidor:id,nombre,apellido');
    }

    public function rutaArchivo(ComentarioTicket $comentario): ?string
    {
        return $comentario->archivo_ruta && Storage::disk('local')->exists($comentario->archivo_ruta)
            ? $comentario->archivo_ruta
            : null;
    }

    public function catalogos(): array
    {
        $tic = UnidadAdministrativa::where('codigo', config('helpdesk.unidad_tic_codigo'))->first();

        return [
            'categorias'  => CategoriaTicket::where('activa', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'es_hardware', 'descripcion', 'unidad_tic_id']),
            'prioridades' => collect(PrioridadTicket::cases())->map(fn ($p) => ['valor' => $p->value, 'etiqueta' => $p->etiqueta()])->all(),
            'subunidades' => $tic
                ? UnidadAdministrativa::where('unidad_padre_id', $tic->id)->orderBy('nombre')->get(['id', 'nombre'])
                : [],
            'tecnicos'    => User::permission(Permiso::GESTIONAR_TICKETS->value)->where('activo', true)
                ->with('servidor:id,nombre,apellido')->get()
                ->map(fn (User $u) => ['id' => $u->id, 'nombre' => $u->nombre_completo])
                ->sortBy('nombre')->values()->all(),
        ];
    }

    /** Desde el Portal, el equipo tiene que ser uno que tiene a su cargo o usa. */
    private function exigirEquipoPropio(int $servidorId, int $bienId): void
    {
        $suyo = AsignacionBien::where('bien_informatico_id', $bienId)
            ->where('estado', AsignacionBien::ESTADO_ACTIVA)
            ->where(fn (Builder $q) => $q->where('servidor_id', $servidorId)->orWhere('usuario_servidor_id', $servidorId))
            ->exists();

        if (! $suyo) {
            throw ValidationException::withMessages(['bien_informatico_id' => 'Elija uno de los equipos que tiene a su cargo o usa.']);
        }
    }

    private function guardarComentario(Ticket $ticket, User $user, string $texto, bool $interno, ?UploadedFile $archivo): ComentarioTicket
    {
        $comentario = ComentarioTicket::create([
            'ticket_id'  => $ticket->id,
            'user_id'    => $user->id,
            'comentario' => $texto,
            'es_interno' => $interno,
        ]);

        if ($archivo) {
            $ruta = $archivo->store("helpdesk/tickets/{$ticket->id}", 'local')
                ?: throw new ReglaNegocioException('No se pudo guardar el archivo. Inténtelo de nuevo.');
            $comentario->update([
                'archivo_ruta'   => $ruta,
                'archivo_nombre' => $archivo->getClientOriginalName(),
                'archivo_mime'   => $archivo->getMimeType(),
            ]);
        }

        return $comentario;
    }

    /** TIC-AAAA-NNNNN, con el candado de aviso de los demás folios. `withTrashed`: un ticket borrado no libera su número. */
    private function siguienteFolio(): string
    {
        $anio = (int) now()->format('Y');
        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["ticket_folio_{$anio}"]);

        $ultimo = Ticket::withTrashed()->where('codigo_ticket', 'like', "TIC-{$anio}-%")->max('codigo_ticket');
        $secuencial = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('TIC-%d-%05d', $anio, $secuencial);
    }
}
