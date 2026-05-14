<?php

namespace App\Http\Controllers\Evaluacion;

use App\Contracts\Evaluacion\EvaluacionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluacion\RegistrarResultadoRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class EvaluacionController extends Controller
{
    public function __construct(private EvaluacionServiceInterface $evaluacionService) {}

    public function registrarResultado(int $evaluacionId, int $servidorId, RegistrarResultadoRequest $request): JsonResponse
    {
        $resultado = $this->evaluacionService->registrarResultado(
            $evaluacionId,
            $servidorId,
            $request->validated(),
            $request->user()->id
        );

        $mensaje = 'Calificación registrada exitosamente bajo la escala MRL: ' . strtoupper($resultado->calificacion_cualitativa->value);
        
        // Alerta visual en la respuesta si disparó sumario
        if (in_array($resultado->calificacion_cualitativa->value, ['insuficiente'])) {
            $mensaje .= '. ALERTA: Se ha iniciado automáticamente un Sumario Administrativo por calificación insuficiente.';
        }

        return ApiResponse::ok($resultado->load('planMejora'), $mensaje);
    }
}
