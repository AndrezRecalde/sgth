<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\Beneficiario;
use App\Http\Requests\Dispensario\StoreBeneficiarioRequest;
use App\Http\Resources\Dispensario\BeneficiarioResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BeneficiarioController extends Controller
{
    /**
     * Lista los beneficiarios del servidor autenticado.
     */
    public function misBeneficiarios(): JsonResponse
    {
        $servidorId = auth()->user()->servidor_id;

        if (!$servidorId) {
            return ApiResponse::error('El usuario no tiene un perfil de servidor asociado.', 403);
        }

        $beneficiarios = Beneficiario::where('servidor_id', $servidorId)->get();

        return ApiResponse::ok(BeneficiarioResource::collection($beneficiarios));
    }

    /**
     * Registra un beneficiario para el servidor autenticado.
     */
    public function storeMisBeneficiarios(StoreBeneficiarioRequest $request): JsonResponse
    {
        $servidorId = auth()->user()->servidor_id;

        if (!$servidorId) {
            return ApiResponse::error('El usuario no tiene un perfil de servidor asociado.', 403);
        }

        $datos = array_merge($request->validated(), ['servidor_id' => $servidorId]);
        $beneficiario = Beneficiario::create($datos);

        return ApiResponse::created(new BeneficiarioResource($beneficiario), 'Beneficiario registrado exitosamente.');
    }

    /**
     * Actualiza un beneficiario del servidor autenticado.
     */
    public function updateMisBeneficiarios(StoreBeneficiarioRequest $request, int $id): JsonResponse
    {
        $servidorId = auth()->user()->servidor_id;
        $beneficiario = Beneficiario::where('servidor_id', $servidorId)->findOrFail($id);
        
        $beneficiario->update($request->validated());

        return ApiResponse::ok(new BeneficiarioResource($beneficiario), 'Beneficiario actualizado exitosamente.');
    }

    /**
     * Elimina un beneficiario del servidor autenticado.
     */
    public function destroyMisBeneficiarios(int $id): JsonResponse
    {
        $servidorId = auth()->user()->servidor_id;
        $beneficiario = Beneficiario::where('servidor_id', $servidorId)->findOrFail($id);
        
        $beneficiario->delete();

        return ApiResponse::ok([], 'Beneficiario eliminado exitosamente.');
    }

    // --- MÉTODOS PARA UATH ---

    public function indexUath(int $servidorId): JsonResponse
    {
        $beneficiarios = Beneficiario::where('servidor_id', $servidorId)->get();
        return ApiResponse::ok(BeneficiarioResource::collection($beneficiarios));
    }

    public function storeUath(StoreBeneficiarioRequest $request, int $servidorId): JsonResponse
    {
        $datos = array_merge($request->validated(), ['servidor_id' => $servidorId]);
        $beneficiario = Beneficiario::create($datos);

        return ApiResponse::created(new BeneficiarioResource($beneficiario), 'Beneficiario registrado exitosamente.');
    }

    public function updateUath(StoreBeneficiarioRequest $request, int $servidorId, int $id): JsonResponse
    {
        $beneficiario = Beneficiario::where('servidor_id', $servidorId)->findOrFail($id);
        $beneficiario->update($request->validated());

        return ApiResponse::ok(new BeneficiarioResource($beneficiario), 'Beneficiario actualizado exitosamente.');
    }

    public function destroyUath(int $servidorId, int $id): JsonResponse
    {
        $beneficiario = Beneficiario::where('servidor_id', $servidorId)->findOrFail($id);
        $beneficiario->delete();

        return ApiResponse::ok([], 'Beneficiario eliminado exitosamente.');
    }
}
