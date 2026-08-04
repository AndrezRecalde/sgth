<?php

namespace App\Contracts\Seleccion;

use App\Models\Seleccion\EvaluacionSeleccion;
use App\Models\Seleccion\Postulante;
use Illuminate\Support\Collection;

interface SeleccionServiceInterface
{
    /**
     * Registra o actualiza la calificación (méritos y oposición) de un postulante.
     */
    public function calificarPostulante(int $postulanteId, array $datos, int $evaluadorId): EvaluacionSeleccion;

    /**
     * Declara uno o varios ganadores y los despacha al dispensario médico.
     *
     * En un concurso formal el número de ganadores está acotado por las
     * vacantes, el resto de aprobados pasa a lista de espera y la convocatoria
     * queda en evaluación médica. En un contenedor express no hay competencia
     * entre aspirantes: se despacha a los indicados sin tocar a los demás ni
     * el estado del contenedor, que es permanente.
     *
     * @param  list<int>  $postulanteIds
     * @return \Illuminate\Support\Collection<int, Postulante>
     */
    public function declararGanadores(int $convocatoriaId, array $postulanteIds, int $userId): Collection;
}
