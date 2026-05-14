<?php

namespace App\Http\Controllers\Disciplinario;

use App\Contracts\Disciplinario\DisciplinarioServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Disciplinario\ResolverSumarioRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class DisciplinarioController extends Controller
{
    public function __construct(private DisciplinarioServiceInterface $disciplinarioService) {}

    public function resolver(int $sumarioId, ResolverSumarioRequest $request): JsonResponse
    {
        $sumario = $this->disciplinarioService->resolverSumario(
            $sumarioId,
            $request->validated(),
            $request->user()->id
        );

        $mensaje = 'Sumario Administrativo resuelto exitosamente y sanción aplicada.';
        if ($request->validated('tipo_sancion') === 'destitucion') {
            $mensaje .= ' ALERTA: Servidor destituido, movimiento de egreso generado automáticamente.';
        }

        return ApiResponse::ok($sumario->load('sancion'), $mensaje);
    }
}
