<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\Ticket;
use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly HelpdeskServiceInterface $service)
    {
    }

    /**
     * Los tickets que el usuario puede ver (`Ticket::scopeVisiblesPara`).
     *
     * Antes devolvía los de toda la institución a cualquier usuario, y
     * precargaba `bien_informatico`, que no es una relación: en cuanto hubiera
     * un ticket, el listado reventaba.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $tickets = Ticket::visiblesPara($request->user())
            ->with(['categoria', 'sla', 'tecnico.servidor', 'bien'])
            ->latest()
            ->orderByDesc('id')
            ->get();

        return ApiResponse::ok($tickets, 'Tickets listados exitosamente.');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'categoria_id' => 'required|exists:categorias_ticket,id',
            'sla_id' => 'required|exists:slas,id',
            'asunto' => 'required|string|max:200',
            'descripcion' => 'required|string',
            'prioridad' => 'required|string|max:50',
            'bien_informatico_id' => 'nullable|exists:bienes_informaticos,id'
        ]);

        $ticket = $this->service->crearTicket($validated);

        return ApiResponse::created($ticket, 'Ticket creado exitosamente.');
    }

    public function show(Request $request, int $id)
    {
        // `bien_informatico` no es una relación: la del modelo se llama `bien`.
        // Con eso y la de comentarios, que faltaba, el detalle de un ticket
        // reventaba en cuanto existía el ticket.
        $ticket = Ticket::with([
            'categoria', 'sla', 'tecnico.servidor', 'bien',
            'comentarios.user.servidor',
        ])->findOrFail($id);
        $this->authorize('view', $ticket);

        // Las notas internas son la conversación entre quienes atienden: el
        // detalle las cargaba para cualquiera que pudiera abrir el ticket.
        if (! $request->user()->can('verNotasInternas', $ticket)) {
            $ticket->setRelation(
                'comentarios',
                $ticket->comentarios->where('es_interno', false)->values()
            );
        }

        return ApiResponse::ok($ticket, 'Detalle del ticket.');
    }

    public function update(Request $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('atender', $ticket);
        $ticket->update($request->only(['asunto', 'descripcion']));
        return ApiResponse::ok($ticket, 'Ticket actualizado correctamente.');
    }

    public function cambiarEstado(Request $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('atender', $ticket);
        $validated = $request->validate(['estado' => 'required|string|max:50']);
        $ticket->update(['estado' => $validated['estado']]);
        return ApiResponse::ok($ticket, 'Estado del ticket cambiado.');
    }

    public function asignar(Request $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);
        $this->authorize('asignar', $ticket);
        $validated = $request->validate(['tecnico_dtic_id' => 'required|exists:tecnicos_dtic,id']);
        $ticket->update(['tecnico_dtic_id' => $validated['tecnico_dtic_id'], 'estado' => 'asignado']);
        return ApiResponse::ok($ticket, 'Ticket asignado exitosamente.');
    }

    public function escalar(Request $request, int $id)
    {
        $this->authorize('atender', Ticket::findOrFail($id));
        $validated = $request->validate(['nivel' => 'required|integer|min:2|max:3']);
        $ticket = $this->service->escalarTicket($id, $validated['nivel']);
        return ApiResponse::ok($ticket, 'Ticket escalado exitosamente.');
    }

    public function vincularBien(Request $request, int $id)
    {
        $this->authorize('atender', Ticket::findOrFail($id));
        $validated = $request->validate([
            'bien_informatico_id' => 'required|exists:bienes_informaticos,id'
        ]);
        
        $ticket = $this->service->vincularBienATicket($id, $validated['bien_informatico_id']);
        
        return ApiResponse::ok($ticket, 'Bien informático vinculado al ticket exitosamente.');
    }

    public function cerrar(Request $request, int $id)
    {
        $this->authorize('atender', Ticket::findOrFail($id));
        $ticket = $this->service->cerrarTicket($id, $request->all());
        return ApiResponse::ok($ticket, 'Ticket cerrado exitosamente.');
    }
}