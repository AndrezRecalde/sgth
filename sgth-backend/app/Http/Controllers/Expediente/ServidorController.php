<?php

namespace App\Http\Controllers\Expediente;

use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreServidorRequest;
use App\Http\Requests\Expediente\UpdateServidorRequest;
use App\Http\Resources\Expediente\ServidorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServidorController extends Controller
{
    private ExpedienteServiceInterface $expedienteService;

    public function __construct(ExpedienteServiceInterface $expedienteService)
    {
        $this->expedienteService = $expedienteService;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('verAny', Servidor::class);

        $filtros = $request->only(['unidad_administrativa_id', 'estado', 'tipo_nombramiento', 'tiene_discapacidad']);
        $servidores = $this->expedienteService->listarServidores($filtros);

        return ApiResponse::ok(ServidorResource::collection($servidores), 'Listado de servidores');
    }

    public function store(StoreServidorRequest $request): JsonResponse
    {
        $this->authorize('crear', Servidor::class);

        $servidor = $this->expedienteService->crearServidor($request->validated());

        return ApiResponse::created(new ServidorResource($servidor), 'Expediente del servidor creado con éxito.');
    }

    public function show(int $id): JsonResponse
    {
        $servidor = $this->expedienteService->obtenerExpedienteCompleto($id);

        $this->authorize('ver', $servidor);

        return ApiResponse::ok(new ServidorResource($servidor), 'Expediente detallado');
    }

    public function update(UpdateServidorRequest $request, int $id): JsonResponse
    {
        $servidorModel = Servidor::findOrFail($id);
        $this->authorize('actualizar', $servidorModel);

        $servidor = $this->expedienteService->actualizarServidor($id, $request->validated());

        return ApiResponse::ok(new ServidorResource($servidor), 'Expediente actualizado exitosamente.');
    }
}
