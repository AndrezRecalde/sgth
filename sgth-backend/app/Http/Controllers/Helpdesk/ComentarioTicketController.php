<?php

namespace App\Http\Controllers\Helpdesk;

use App\Contracts\Helpdesk\TicketServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\ComentarTicketRequest;
use App\Http\Resources\Helpdesk\ComentarioTicketResource;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\ComentarioTicket;
use App\Models\Helpdesk\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La conversación de un ticket y sus archivos (fase 3.1). Las notas internas
 * son entre quienes atienden: el solicitante ni las lee ni puede marcar una.
 */
final class ComentarioTicketController extends Controller
{
    public function __construct(private readonly TicketServiceInterface $tickets) {}

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $comentarios = ComentarioTicket::where('ticket_id', $ticket->id)
            ->with('user:id,usuario_ti,email,servidor_id', 'user.servidor:id,nombre,apellido')
            ->when(! $request->user()->can('verNotasInternas', $ticket), fn ($q) => $q->where('es_interno', false))
            ->oldest()
            ->orderBy('id')
            ->get();

        return ApiResponse::ok(ComentarioTicketResource::collection($comentarios), 'Conversación del ticket.');
    }

    public function store(ComentarTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('comentar', $ticket);
        $interno = $request->boolean('es_interno') && $request->user()->can('verNotasInternas', $ticket);

        $comentario = $this->tickets->comentar($ticket, $request->user(), $request->validated('comentario'), $interno, $request->file('archivo'));

        return ApiResponse::created(new ComentarioTicketResource($comentario), $interno ? 'Nota interna agregada.' : 'Mensaje enviado.');
    }

    public function archivo(Request $request, Ticket $ticket, ComentarioTicket $comentario): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $ticket);

        if ((int) $comentario->ticket_id !== (int) $ticket->id
            || ($comentario->es_interno && ! $request->user()->can('verNotasInternas', $ticket))) {
            return ApiResponse::noEncontrado('Ese archivo no es de este ticket.');
        }

        $ruta = $this->tickets->rutaArchivo($comentario);
        if (! $ruta) {
            return ApiResponse::noEncontrado('El mensaje no tiene archivo, o ya no está.');
        }

        return Storage::disk('local')->download($ruta, $comentario->archivo_nombre ?? 'archivo');
    }
}
