<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\ComentarioTicket;
use App\Models\Helpdesk\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los comentarios de un ticket.
 *
 * `store` era un muñón: respondía «Comentario agregado» y no guardaba nada, ni
 * siquiera recibía el ticket. Quien comentaba veía la confirmación y el
 * comentario se perdía. El modelo y la tabla estaban completos desde el
 * principio; lo único que faltaba era el controlador.
 */
class ComentarioTicketController extends Controller
{
    public function index(Request $request, int $ticketId): JsonResponse
    {
        // Antes bastaba con que el ticket existiera: cualquier usuario leía la
        // conversación de un ticket ajeno.
        $ticket = Ticket::findOrFail($ticketId);
        $this->authorize('view', $ticket);

        $comentarios = ComentarioTicket::where('ticket_id', $ticketId)
            ->with('user.servidor:id,nombre,apellido')
            // Una nota interna es la conversación entre técnicos sobre el
            // ticket; el solicitante no debe leerla. Sin este filtro, el
            // listado la habría entregado a quien abrió el ticket.
            ->when(
                ! $request->user()->can('verNotasInternas', $ticket),
                fn ($q) => $q->where('es_interno', false)
            )
            ->oldest()
            ->get();

        return ApiResponse::ok($comentarios, 'Comentarios del ticket.');
    }

    public function store(Request $request, int $ticketId): JsonResponse
    {
        $ticket = Ticket::findOrFail($ticketId);
        $this->authorize('comentar', $ticket);

        $datos = $request->validate([
            'comentario'    => ['required', 'string', 'max:2000'],
            'es_interno'    => ['nullable', 'boolean'],
            'evidencia_url' => ['nullable', 'string', 'max:255'],
        ]);

        $comentario = ComentarioTicket::create([
            'ticket_id'     => $ticketId,
            'user_id'       => $request->user()->id,
            'comentario'    => $datos['comentario'],
            // Marcar un comentario como interno es cosa de quien atiende: si lo
            // pudiera hacer el solicitante, escondería su propio mensaje del
            // técnico que tiene que leerlo.
            'es_interno'    => $request->user()->can('verNotasInternas', $ticket)
                && (bool) ($datos['es_interno'] ?? false),
            'evidencia_url' => $datos['evidencia_url'] ?? null,
        ]);

        return ApiResponse::created(
            $comentario->load('user.servidor:id,nombre,apellido'),
            'Comentario agregado.'
        );
    }
}
