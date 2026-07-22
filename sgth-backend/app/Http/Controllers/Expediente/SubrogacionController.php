<?php

namespace App\Http\Controllers\Expediente;

use App\Contracts\Expediente\SubrogacionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Expediente\Subrogacion;

class SubrogacionController extends Controller
{
    private SubrogacionServiceInterface $subrogacionService;

    public function __construct(SubrogacionServiceInterface $subrogacionService)
    {
        $this->subrogacionService = $subrogacionService;
    }

    public function registrar(Request $request): JsonResponse
    {
        // La validación en el controlador se delega o se usa FormRequest. Aquí validamos básico.
        $this->authorize('registrar', Subrogacion::class);

        $datos = $request->validate([
            'tipo'                     => 'required|string|in:subrogacion,encargo',
            'servidor_subrogante_id'   => 'required|exists:servidores,id',
            'servidor_subrogado_id'    => 'nullable|exists:servidores,id',
            'unidad_administrativa_id' => 'required|exists:unidades_administrativas,id',
            'puesto_subrogado_id'      => 'required|exists:puestos,id',
            'fecha_inicio'             => 'required|date',
            'fecha_fin'                => 'required|date|after:fecha_inicio',
            'motivo'                   => 'required|string',
            'resolucion_numero'        => 'nullable|string',
            'documento_respaldo'       => 'nullable|string',
            'observacion'              => 'nullable|string',
        ]);

        $subrogacion = $this->subrogacionService->registrar($datos);

        return ApiResponse::created($subrogacion, 'Subrogación/Encargo registrado exitosamente.');
    }

    public function finalizar(int $id): JsonResponse
    {
        $this->authorize('finalizar', Subrogacion::class);

        $subrogacion = $this->subrogacionService->finalizar($id);

        return ApiResponse::ok($subrogacion, 'Subrogación/Encargo finalizado correctamente.');
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->authorize('cancelar', Subrogacion::class);

        $datos = $request->validate([
            'motivo' => 'required|string|min:5',
        ]);

        $subrogacion = $this->subrogacionService->cancelar($id, $datos['motivo']);

        return ApiResponse::ok($subrogacion, 'Subrogación/Encargo cancelado exitosamente.');
    }

    public function listarActivas(Request $request): JsonResponse
    {
        $this->authorize('verAny', Subrogacion::class);

        $activas = $this->subrogacionService->listarActivas(
            $request->only(['unidad_administrativa_id', 'tipo'])
        );

        return ApiResponse::ok($activas, 'Subrogaciones activas');
    }

    public function listarPorServidor(int $servidorId): JsonResponse
    {
        $this->authorize('verAny', Subrogacion::class);

        $historial = $this->subrogacionService->listarPorServidor($servidorId);

        return ApiResponse::ok($historial, 'Historial de subrogaciones');
    }
}
