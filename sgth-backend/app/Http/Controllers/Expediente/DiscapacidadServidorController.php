<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreDiscapacidadServidorRequest;
use App\Http\Requests\Expediente\UpdateDiscapacidadServidorRequest;
use App\Models\Expediente\DiscapacidadServidor;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\DiscapacidadServidorService;
use Illuminate\Http\JsonResponse;
use App\Http\Responses\ApiResponse;

/**
 * Las discapacidades del servidor.
 *
 * Desde el 2026-10-03 responde con ApiResponse como el resto: el alta y la
 * edición devolvían `{message, data}`, y el frontend, que lee `datos`,
 * recibía `undefined`. El `show` se retiró: ninguna pantalla lo pedía.
 */
class DiscapacidadServidorController extends Controller
{
    public function __construct(private DiscapacidadServidorService $discapacidadService)
    {
    }

    public function index(int $servidorId): JsonResponse
    {
        $discapacidades = $this->discapacidadService->listar($servidorId);
        return ApiResponse::ok($discapacidades, 'Discapacidades del servidor.');
    }

    public function store(StoreDiscapacidadServidorRequest $request, int $servidorId): JsonResponse
    {
        // Sin esto, un servidor inexistente reventaba la FK con un 500.
        Servidor::findOrFail($servidorId);

        $discapacidad = $this->discapacidadService->crear($servidorId, $request->validated());
        return ApiResponse::created($discapacidad, 'Discapacidad registrada con éxito.');
    }

    public function update(UpdateDiscapacidadServidorRequest $request, int $servidorId, DiscapacidadServidor $discapacidade): JsonResponse
    {
        if ($discapacidade->servidor_id !== (int) $servidorId) {
            abort(404, 'Discapacidad no encontrada para este servidor.');
        }

        $discapacidadActualizada = $this->discapacidadService->actualizar($discapacidade, $request->validated());

        return ApiResponse::ok($discapacidadActualizada, 'Discapacidad actualizada con éxito.');
    }

    public function destroy(int $servidorId, DiscapacidadServidor $discapacidade): JsonResponse
    {
        if ($discapacidade->servidor_id !== (int) $servidorId) {
            abort(404, 'Discapacidad no encontrada para este servidor.');
        }

        $this->discapacidadService->eliminar($discapacidade);

        return ApiResponse::ok(null, 'Discapacidad eliminada con éxito.');
    }
}
