<?php

namespace App\Contracts\Seleccion;

use App\Models\Seleccion\EvaluacionSeleccion;
use App\Models\Seleccion\Postulante;

interface SeleccionServiceInterface
{
    /**
     * Registra o actualiza la calificación (méritos y oposición) de un postulante.
     */
    public function calificarPostulante(int $postulanteId, array $datos, int $evaluadorId): EvaluacionSeleccion;

    /**
     * Declara a un postulante como ganador del concurso, cambia los estados, 
     * e inserta un registro en movimientos de personal (ingreso).
     */
    public function declararGanador(int $convocatoriaId, int $postulanteGanadorId, int $userId): Postulante;
}
