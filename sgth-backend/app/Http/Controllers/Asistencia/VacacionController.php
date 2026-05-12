<?php

namespace App\Http\Controllers\Asistencia;

use App\Contracts\Asistencia\VacacionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\StoreVacacionRequest;
use App\Http\Requests\Asistencia\UpdateVacacionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\Vacacion;
use Illuminate\Http\Request;

class VacacionController extends Controller
{
    public function __construct(private VacacionServiceInterface $vacacionService) {}

    public function index(Request $request)
    {
        $vacaciones = Vacacion::with('servidor')->orderBy('created_at', 'desc')->get();
        return ApiResponse::ok($vacaciones, 'Listado de solicitudes de vacaciones');
    }

    public function saldo(int $servidorId)
    {
        $saldo = $this->vacacionService->calcularSaldoActual($servidorId);
        return ApiResponse::ok(['saldo_dias' => $saldo], 'Saldo de vacaciones calculado exitosamente.');
    }

    public function store(StoreVacacionRequest $request)
    {
        $servidorId = $request->input('servidor_id') ?? ($request->user()->servidor->id ?? 1);
        
        $vacacion = $this->vacacionService->solicitar($request->validated(), $servidorId);
        
        return ApiResponse::created($vacacion, 'Solicitud de vacaciones generada.');
    }

    public function update(UpdateVacacionRequest $request, int $id)
    {
        $vacacion = Vacacion::findOrFail($id);
        $vacacion->estado = $request->validated('estado');
        
        if ($vacacion->estado === 'aprobada') {
            $vacacion->aprobado_por = $request->user()->id;
        }
        
        $vacacion->save();

        return ApiResponse::ok($vacacion, "La solicitud ha sido resuelta como {$vacacion->estado}.");
    }
}
