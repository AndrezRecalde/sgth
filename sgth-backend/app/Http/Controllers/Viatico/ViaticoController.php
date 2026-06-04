<?php

namespace App\Http\Controllers\Viatico;

use App\Contracts\Viatico\ViaticoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Viatico\LiquidarViaticoRequest;
use App\Http\Requests\Viatico\SolicitarViaticoRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ViaticoController extends Controller
{
    public function __construct(private ViaticoServiceInterface $viaticoService) {}

    public function solicitar(int $servidorId, SolicitarViaticoRequest $request): JsonResponse
    {
        $viatico = $this->viaticoService->solicitar(
            $servidorId,
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created($viatico, 'Solicitud de viático creada con éxito. El monto ha sido calculado automáticamente basado en la normativa del MRL.');
    }

    public function liquidar(int $viaticoId, LiquidarViaticoRequest $request): JsonResponse
    {
        $liquidacion = $this->viaticoService->liquidar(
            $viaticoId,
            $request->validated(),
            $request->user()->id
        );

        $viatico = $liquidacion->viatico;

        return ApiResponse::ok([
            'liquidacion' => $liquidacion,
            'viatico' => $viatico
        ], 'Viático liquidado correctamente. Facturas procesadas considerando el 70/30 de la normativa del MRL.');
    }

    public function aprobar(int $id): JsonResponse
    {
        $viatico = \App\Models\Viatico\Viatico::findOrFail($id);
        $this->authorize('gestionar-viaticos');

        if ($viatico->estado->value !== 'solicitado') {
            return ApiResponse::error(
                'Solo se pueden aprobar viáticos en estado solicitado.',
                422
            );
        }

        // Modalidad anticipo
        $montoAnticipo = match($viatico->modalidad_anticipo) {
            'total'        => $viatico->monto_calculado,
            'parcial'      => $viatico->monto_anticipo, // ya definido
            'sin_anticipo' => 0.00,
            default        => $viatico->monto_calculado,
        };

        $viatico->update([
            'estado'         => \App\Enums\EstadoViatico::APROBADO,
            'monto_anticipo' => $montoAnticipo,
        ]);

        return ApiResponse::ok(
            $viatico->fresh(),
            'Viático aprobado correctamente.'
        );
    }
}
