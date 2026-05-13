<?php
namespace App\Http\Controllers\Helpdesk;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use Illuminate\Http\Request;
class TicketController extends Controller
{
    public function __construct(private readonly HelpdeskServiceInterface $service)
    {
    }
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
    }
}