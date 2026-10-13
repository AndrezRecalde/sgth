<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\ReglasPrestamoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\ReglasPrestamoRequest;
use App\Http\Resources\InventarioTi\ReglasPrestamoResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Las reglas del préstamo (D16). Las lee también el Portal, para armar el
 * formulario de la solicitud; las cambia TIC.
 */
final class ReglasPrestamoController extends Controller
{
    public function __construct(private readonly ReglasPrestamoServiceInterface $reglas) {}

    public function show(): JsonResponse
    {
        return ApiResponse::ok(new ReglasPrestamoResource($this->reglas->obtener()), 'Reglas del préstamo de equipos.');
    }

    public function update(ReglasPrestamoRequest $request): JsonResponse
    {
        return ApiResponse::ok(new ReglasPrestamoResource($this->reglas->actualizar($request->validated())), 'Reglas del préstamo actualizadas.');
    }
}
