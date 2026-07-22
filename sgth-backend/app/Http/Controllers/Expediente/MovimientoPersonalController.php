<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreMovimientoPersonalRequest;
use App\Http\Resources\Expediente\MovimientoPersonalResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\MovimientoPersonalService;
use Illuminate\Http\JsonResponse;

class MovimientoPersonalController extends Controller
{
    public function __construct(private MovimientoPersonalService $movimientoService)
    {
    }

    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);

        $this->authorize('ver', $servidor);

        $movimientos = MovimientoPersonal::with(['unidadOrigen', 'unidadDestino', 'puestoOrigen.cargo', 'puestoDestino.cargo', 'autorizadoPor'])
            ->where('servidor_id', $servidorId)
            ->orderBy('fecha_efectiva', 'desc')
            ->get();

        return ApiResponse::ok(
            MovimientoPersonalResource::collection($movimientos),
            'Historial inmutable de movimientos'
        );
    }

    public function store(StoreMovimientoPersonalRequest $request, int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);

        $this->authorize('actualizar', $servidor);

        $movimiento = $this->movimientoService->registrar(
            $servidorId, $request->validated()
        );

        return ApiResponse::created(
            new MovimientoPersonalResource($movimiento),
            'Movimiento registrado con éxito.'
        );
    }
}
