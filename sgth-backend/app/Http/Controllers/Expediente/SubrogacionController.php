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

    public function listarVigentes(Request $request): JsonResponse
    {
        $this->authorize('verAny', Subrogacion::class);

        $vigentes = $this->subrogacionService->listarVigentes(
            $request->only(['unidad_administrativa_id', 'tipo', 'per_page', 'page'])
        );

        // `datos` y `meta` por separado, como el resto de los listados
        // paginados del expediente: la tabla necesita el total para dibujar su
        // paginador, y devolver el paginador entero metía sus enlaces dentro de
        // `datos`.
        return response()->json([
            'exito'   => true,
            'mensaje' => 'Subrogaciones pendientes y activas',
            'datos'   => $vigentes->items(),
            'meta'    => [
                'pagina_actual' => $vigentes->currentPage(),
                'por_pagina'    => $vigentes->perPage(),
                'total'         => $vigentes->total(),
                'ultima_pagina' => $vigentes->lastPage(),
            ],
        ]);
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
