<?php

namespace App\Http\Controllers\Expediente;

use App\Contracts\Expediente\SubrogacionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\CancelarSubrogacionRequest;
use App\Http\Requests\Expediente\RegistrarSubrogacionRequest;
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

    public function registrar(RegistrarSubrogacionRequest $request): JsonResponse
    {
        $this->authorize('registrar', Subrogacion::class);

        $subrogacion = $this->subrogacionService->registrar($request->validated());

        return ApiResponse::created($subrogacion, 'Subrogación/Encargo registrado exitosamente.');
    }

    public function finalizar(int $id): JsonResponse
    {
        $this->authorize('finalizar', Subrogacion::class);

        $subrogacion = $this->subrogacionService->finalizar($id);

        return ApiResponse::ok($subrogacion, 'Subrogación/Encargo finalizado correctamente.');
    }

    public function cancelar(CancelarSubrogacionRequest $request, int $id): JsonResponse
    {
        $this->authorize('cancelar', Subrogacion::class);

        $subrogacion = $this->subrogacionService->cancelar($id, $request->validated()['motivo']);

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

    public function listarVigentes(Request $request): JsonResponse
    {
        $this->authorize('verAny', Subrogacion::class);

        $vigentes = $this->subrogacionService->listarVigentes(
            $request->only(['unidad_administrativa_id', 'tipo'])
        );

        return ApiResponse::ok($vigentes, 'Subrogaciones pendientes y activas');
    }

    /**
     * Historial de un servidor en ambos papeles. Lo abre quien administra o
     * audita, y el propio interesado: de eso se encarga el policy, porque
     * depende de qué servidor se pide y el middleware de la ruta no lo sabe.
     */
    public function listarPorServidor(int $servidorId): JsonResponse
    {
        $this->authorize('verDeServidor', [Subrogacion::class, $servidorId]);

        $historial = $this->subrogacionService->listarPorServidor($servidorId);

        return ApiResponse::ok($historial, 'Historial de subrogaciones');
    }
}
