<?php
namespace App\Http\Controllers\Asistencia;

use App\Contracts\Asistencia\VacacionServiceInterface;
use App\Enums\MotivoVacacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\StoreVacacionRequest;
use App\Http\Requests\Asistencia\UpdateVacacionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\Vacacion;
use Illuminate\Http\Request;

class VacacionController extends Controller
{
    public function __construct(
        private VacacionServiceInterface $vacacionService
    ) {}

    public function index(Request $request)
    {
        $query = Vacacion::with([
            'servidor',
            'jefe',
            'creadoPor',
            'unidadAdministrativa',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('servidor_id')) {
            $query->where('servidor_id', $request->servidor_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('motivo')) {
            $query->where('motivo', $request->motivo);
        }

        $vacaciones = $query->paginate(20);
        return ApiResponse::ok($vacaciones, 'Listado de solicitudes de vacaciones');
    }

    public function saldo(int $servidorId)
    {
        $saldo = $this->vacacionService->calcularSaldoActual($servidorId);
        return ApiResponse::ok(['saldo_dias' => $saldo], 'Saldo de vacaciones calculado.');
    }

    public function store(StoreVacacionRequest $request)
    {
        $servidorId = $request->input('servidor_id')
            ?? ($request->user()->servidor->id ?? null);

        if (!$servidorId) {
            return ApiResponse::error('No se identificó el servidor.', 422);
        }

        $datos = array_merge($request->validated(), [
            'creado_por'    => $request->user()->id,
            'fecha_emision' => now()->toDateString(),
        ]);

        $vacacion = $this->vacacionService->solicitar($datos, $servidorId);
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
        return ApiResponse::ok(
            $vacacion->fresh(['servidor', 'jefe', 'creadoPor']),
            "Solicitud resuelta como {$vacacion->estado}."
        );
    }
}
