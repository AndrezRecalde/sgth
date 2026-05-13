<?php
<<<<<<< HEAD
namespace App\Http\Controllers\Helpdesk;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use Illuminate\Http\Request;
=======

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\Ticket;
use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use Illuminate\Http\Request;

>>>>>>> feature/sprint-10-inventario-helpdesk
class TicketController extends Controller
{
    public function __construct(private readonly HelpdeskServiceInterface $service)
    {
    }
<<<<<<< HEAD
    public function index()
    {
        return ApiResponse::ok([], 'Tickets listados');
    }
    public function store(Request $request)
    {
        return ApiResponse::created($this->service->crearTicket($request->all()), 'Ticket creado');
    }
    public function update(Request $request, int $id)
    {
        return ApiResponse::ok([], 'Ticket actualizado');
    }
    public function cerrar(Request $request, int $id)
    {
        return ApiResponse::ok($this->service->cerrarTicket($id, $request->all()), 'Ticket cerrado');
=======

    public function index()
    {
        $tickets = Ticket::with(['categoria', 'sla', 'tecnico.servidor', 'bien_informatico'])->latest()->get();
        return ApiResponse::ok($tickets, 'Tickets listados exitosamente.');
    }

    public function store(Request $request)
    {
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

    public function show(int $id)
    {
        $ticket = Ticket::with(['categoria', 'sla', 'tecnico.servidor', 'bien_informatico', 'comentarios'])->findOrFail($id);
        return ApiResponse::ok($ticket, 'Detalle del ticket.');
    }

    public function update(Request $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);
        // Lógica estándar de update si corresponde
        $ticket->update($request->only(['asunto', 'descripcion']));
        return ApiResponse::ok($ticket, 'Ticket actualizado correctamente.');
    }

    public function cambiarEstado(Request $request, int $id)
    {
        $validated = $request->validate(['estado' => 'required|string|max:50']);
        $ticket = Ticket::findOrFail($id);
        $ticket->update(['estado' => $validated['estado']]);
        return ApiResponse::ok($ticket, 'Estado del ticket cambiado.');
    }

    public function asignar(Request $request, int $id)
    {
        $validated = $request->validate(['tecnico_dtic_id' => 'required|exists:tecnicos_dtic,id']);
        $ticket = Ticket::findOrFail($id);
        $ticket->update(['tecnico_dtic_id' => $validated['tecnico_dtic_id'], 'estado' => 'asignado']);
        return ApiResponse::ok($ticket, 'Ticket asignado exitosamente.');
    }

    public function escalar(Request $request, int $id)
    {
        $validated = $request->validate(['nivel' => 'required|integer|min:2|max:3']);
        // Se asume que el servicio maneja la reasignación en base al nivel
        $ticket = $this->service->escalarTicket($id, $validated['nivel']);
        return ApiResponse::ok($ticket, 'Ticket escalado exitosamente.');
    }

    public function vincularBien(Request $request, int $id)
    {
        $validated = $request->validate([
            'bien_informatico_id' => 'required|exists:bienes_informaticos,id'
        ]);
        
        // Delegar al servicio para que lo vincule y cambie el estado a en_mantenimiento
        $ticket = $this->service->vincularBienATicket($id, $validated['bien_informatico_id']);
        
        return ApiResponse::ok($ticket, 'Bien informático vinculado al ticket exitosamente.');
    }

    public function cerrar(Request $request, int $id)
    {
        $ticket = $this->service->cerrarTicket($id, $request->all());
        return ApiResponse::ok($ticket, 'Ticket cerrado exitosamente.');
>>>>>>> feature/sprint-10-inventario-helpdesk
    }
}