<?php

namespace App\Contracts\Seleccion;

use App\Enums\EstadoConvocatoria;
use App\Models\Seleccion\Convocatoria;
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

    /**
     * Cierra un concurso formal cuando ya no le queda nada por resolver: no
     * hay ganador esperando dictamen ni incorporación, y o las vacantes están
     * cubiertas o no queda nadie en lista de espera para cubrirlas. Lo llaman
     * la incorporación y el dictamen de no apto. Devuelve el estado en que la
     * dejó (finalizada o desierta), o null si sigue abierta.
     */
    public function cerrarConcursoSiCorresponde(int $convocatoriaId, int $userId): ?EstadoConvocatoria;

    /**
     * El Dispensario declaró no apto a un candidato: queda descalificado. En
     * un express eso cierra su caso; en un concurso formal se cierra la
     * convocatoria si no queda a quién declarar en su lugar.
     */
    public function descalificarPorNoApto(int $postulanteId, int $userId): void;

    /**
     * Envía al Dispensario al siguiente de la lista de espera, por puntaje,
     * para cubrir la vacante que dejó un no apto.
     */
    public function declararSiguiente(int $convocatoriaId, int $userId): Postulante;

    /**
     * Cierra un concurso publicado sin ganadores: desierto (nadie aprobó) o
     * cancelado (por decisión de la institución), siempre con su motivo.
     */
    public function cerrarSinGanadores(
        int $convocatoriaId, EstadoConvocatoria $estado, string $motivo, int $userId
    ): Convocatoria;

    /**
     * Se canceló la solicitud médica de un candidato antes de evaluarlo: vuelve
     * a la lista de espera (formal) o a aprobado (express), de donde se le
     * puede volver a enviar.
     */
    public function devolverPorCancelacion(int $postulanteId): void;
}
