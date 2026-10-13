<?php

namespace App\Http\Controllers\Helpdesk;

use App\Contracts\Helpdesk\TicketEstadoServiceInterface;
use App\Contracts\Helpdesk\TicketServiceInterface;
use App\Enums\EstadoBien;
use App\Enums\PrioridadTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\CrearTicketRequest;
use App\Http\Resources\Helpdesk\TicketResource;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Los tickets de la mesa de ayuda (fase 3.1). El alcance lo pone
 * `TicketPolicy` (y `Ticket::scopeVisiblesPara` en el listado) y el trámite,
 * `TicketEstadoService`: cada paso es una acción con su nombre y no un
 * «cambiar estado» que aceptaba cualquier texto.
 */
final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketServiceInterface $tickets,
        private readonly TicketEstadoServiceInterface $estados,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);
        $pagina = $this->tickets->listar($request->user(), $request->query());

        return ApiResponse::paginadoDe($pagina, TicketResource::collection($pagina->getCollection())->resolve($request), 'Tickets.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = $this->tickets->obtener($id);
        $this->authorize('view', $ticket);

        return ApiResponse::ok($this->paraQuienMira($request, $ticket), 'Detalle del ticket.');
    }

    public function store(CrearTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);
        $ticket = $this->tickets->crear($request->user(), $request->safe()->except('archivo'), $request->file('archivo'));

        return ApiResponse::created(new TicketResource($ticket), "Ticket {$ticket->codigo_ticket} registrado.");
    }

    /** Las categorías, prioridades, subunidades de TIC y técnicos para los formularios. */
    public function catalogos(): JsonResponse
    {
        return ApiResponse::ok($this->tickets->catalogos(), 'Catálogos de la mesa de ayuda.');
    }

    public function asignar(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('asignar', $ticket);
        $datos = $request->validate([
            'tecnico_id'    => ['nullable', 'integer'],
            'unidad_tic_id' => ['nullable', 'integer', Rule::exists('unidades_administrativas', 'id')],
            'prioridad'     => ['nullable', Rule::enum(PrioridadTicket::class)],
        ], [], ['tecnico_id' => 'técnico', 'unidad_tic_id' => 'subunidad de TIC']);

        return $this->respuesta($request, $this->estados->asignar($ticket, $datos), 'Ticket asignado.');
    }

    public function tomar(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('atender', $ticket);

        return $this->respuesta($request, $this->estados->tomar($ticket, $request->user()), 'Ticket en proceso.');
    }

    public function pedirInformacion(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('atender', $ticket);
        $datos = $request->validate(['pregunta' => ['required', 'string', 'min:5', 'max:2000']], [], ['pregunta' => 'pregunta']);

        return $this->respuesta($request, $this->estados->pedirInformacion($ticket, $request->user(), $datos['pregunta']), 'Se pidió información al usuario.');
    }

    public function resolver(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('atender', $ticket);
        $datos = $request->validate([
            'solucion'                => ['required', 'string', 'min:10', 'max:5000'],
            'registrar_mantenimiento' => ['nullable', 'boolean'],
            'estado_equipo'           => ['nullable', Rule::enum(EstadoBien::class)->only([
                EstadoBien::DISPONIBLE, EstadoBien::EN_USO, EstadoBien::EN_MANTENIMIENTO, EstadoBien::EN_GARANTIA,
            ])],
        ], [], ['solucion' => 'solución', 'estado_equipo' => 'estado en que queda el equipo']);

        return $this->respuesta($request, $this->estados->resolver($ticket, $request->user(), $datos), 'Ticket resuelto.');
    }

    public function confirmar(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('responderComoSolicitante', $ticket);
        $datos = $request->validate([
            'calificacion' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comentario'   => ['nullable', 'string', 'max:1000'],
        ], [], ['calificacion' => 'calificación']);

        return $this->respuesta($request, $this->estados->confirmar($ticket, $datos['calificacion'] ?? null, $datos['comentario'] ?? null), 'Gracias: el ticket quedó cerrado.');
    }

    public function reabrir(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('responderComoSolicitante', $ticket);
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:1000']], [], ['motivo' => 'qué sigue fallando']);

        return $this->respuesta($request, $this->estados->reabrir($ticket, $request->user(), $datos['motivo']), 'Ticket reabierto.');
    }

    public function escalar(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('atender', $ticket);
        $datos = $request->validate([
            'nivel'      => ['required', 'integer', 'in:2,3'],
            'motivo'     => ['required', 'string', 'min:5', 'max:1000'],
            'tecnico_id' => ['nullable', 'integer'],
        ], [], ['tecnico_id' => 'técnico']);

        return $this->respuesta($request, $this->estados->escalar($ticket, (int) $datos['nivel'], $datos['motivo'], $datos['tecnico_id'] ?? null), 'Ticket escalado.');
    }

    public function anular(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('anular', $ticket);
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], [], ['motivo' => 'motivo de la anulación']);

        return $this->respuesta($request, $this->estados->anular($ticket, $request->user(), $datos['motivo']), 'Ticket anulado.');
    }

    public function vincularBien(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('atender', $ticket);
        $datos = $request->validate(['bien_informatico_id' => ['nullable', 'integer']], [], ['bien_informatico_id' => 'equipo']);

        return $this->respuesta($request, $this->estados->vincularBien($ticket, $datos['bien_informatico_id'] ?? null), 'Equipo vinculado al ticket.');
    }

    private function respuesta(Request $request, Ticket $ticket, string $mensaje): JsonResponse
    {
        return ApiResponse::ok($this->paraQuienMira($request, $ticket), $mensaje);
    }

    /** Las notas internas son la conversación entre quienes atienden: el solicitante no las recibe. */
    private function paraQuienMira(Request $request, Ticket $ticket): TicketResource
    {
        if ($ticket->relationLoaded('comentarios') && ! $request->user()->can('verNotasInternas', $ticket)) {
            $ticket->setRelation('comentarios', $ticket->comentarios->where('es_interno', false)->values());
        }

        return new TicketResource($ticket);
    }
}
