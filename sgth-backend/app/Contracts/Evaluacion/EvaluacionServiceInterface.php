<?php

namespace App\Contracts\Evaluacion;

use App\Models\Evaluacion\ResultadoEvaluacion;

interface EvaluacionServiceInterface
{
    /**
     * Registra o actualiza el resultado de la evaluación de desempeño para un servidor.
     * Aplica la escala MRL y dispara sumarios disciplinarios automáticamente si procede.
     */
    public function registrarResultado(int $evaluacionId, int $servidorId, array $datos, int $evaluadorId): ResultadoEvaluacion;
}
