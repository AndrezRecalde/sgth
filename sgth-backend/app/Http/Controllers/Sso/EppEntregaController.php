<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Enums\MotivoEntregaEpp;
use App\Services\Sso\EppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

final class EppEntregaController extends Controller
{
    public function __construct(
        private readonly EppService $eppService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $entregas = $this->eppService->listarEntregas($request->all());
        return ApiResponse::paginado($entregas, 'Entregas de EPP obtenidas exitosamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'servidor_id' => ['required', 'integer', 'exists:servidores,id'],
            'equipo_proteccion_id' => ['required', 'integer', 'exists:equipos_proteccion,id'],
            'fecha_entrega' => ['required', 'date', 'before_or_equal:today'],
            'cantidad' => ['nullable', 'integer', 'min:1'],
            'motivo' => ['required', new Enum(MotivoEntregaEpp::class)],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $entrega = $this->eppService->registrarEntrega($validated);
        return ApiResponse::created($entrega, 'Entrega de EPP registrada exitosamente.');
    }

    public function kitParaServidor(int $servidorId): JsonResponse
    {
        $equipos = $this->eppService->listarKitParaServidor($servidorId);
        return ApiResponse::ok($equipos, 'Kit de EPP del puesto obtenido exitosamente.');
    }

    public function storeKit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'servidor_id' => ['required', 'integer', 'exists:servidores,id'],
            'fecha_entrega' => ['required', 'date', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'equipos' => ['required', 'array', 'min:1'],
            'equipos.*.equipo_proteccion_id' => ['required', 'integer', 'exists:equipos_proteccion,id'],
            'equipos.*.cantidad' => ['nullable', 'integer', 'min:1'],
        ]);

        $entregas = $this->eppService->registrarEntregaKit($validated);
        return ApiResponse::created($entregas, 'Kit de EPP entregado exitosamente.');
    }

    public function reporte(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'puesto_id' => ['nullable', 'integer', 'exists:puestos,id'],
        ]);

        $reporte = $this->eppService->reporteEntregas($request->all());
        return ApiResponse::ok($reporte, 'Reporte de EPP entregados generado exitosamente.');
    }
}
