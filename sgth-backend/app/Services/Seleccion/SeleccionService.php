<?php

namespace App\Services\Seleccion;

use App\Contracts\Seleccion\SeleccionServiceInterface;
use App\Enums\EstadoConvocatoria;
use App\Enums\EstadoPostulante;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\EvaluacionSeleccion;
use App\Models\Seleccion\Onboarding;
use App\Models\Seleccion\Postulante;
use Illuminate\Support\Facades\DB;

final class SeleccionService implements SeleccionServiceInterface
{
    public function calificarPostulante(int $postulanteId, array $datos, int $evaluadorId): EvaluacionSeleccion
    {
        $postulante = Postulante::findOrFail($postulanteId);
        
        if ($postulante->convocatoria->estado !== EstadoConvocatoria::EN_EVALUACION) {
            throw new ReglaNegocioException('La convocatoria no está en fase de evaluación.');
        }

        $puntajeTotal = $datos['puntaje_meritos'] + $datos['puntaje_oposicion'];

        $evaluacion = EvaluacionSeleccion::updateOrCreate(
            ['postulante_id' => $postulante->id],
            [
                'puntaje_meritos'   => $datos['puntaje_meritos'],
                'puntaje_oposicion' => $datos['puntaje_oposicion'],
                'puntaje_total'     => $puntajeTotal,
                'observaciones'     => $datos['observaciones'] ?? null,
                'evaluador_id'      => $evaluadorId,
                'updated_by'        => $evaluadorId,
            ]
        );

        // Actualizamos estado si aprueba o reprueba el umbral mínimo de 70/100 (Estándar general)
        $postulante->estado = $puntajeTotal >= 70 ? EstadoPostulante::APROBADO : EstadoPostulante::REPROBADO;
        $postulante->save();

        return $evaluacion;
    }

    public function declararGanador(int $convocatoriaId, int $postulanteGanadorId, int $userId): Postulante
    {
        $convocatoria = Convocatoria::findOrFail($convocatoriaId);
        $ganador = Postulante::where('convocatoria_id', $convocatoriaId)->findOrFail($postulanteGanadorId);

        if ($convocatoria->estado === EstadoConvocatoria::FINALIZADA) {
            throw new ReglaNegocioException('Esta convocatoria ya fue finalizada previamente.');
        }

        if ($ganador->estado !== EstadoPostulante::APROBADO) {
            throw new ReglaNegocioException('El postulante debe estar aprobado (puntaje >= 70) para ganar el concurso.');
        }

        DB::beginTransaction();
        try {
            // 1. Finalizar la convocatoria
            $convocatoria->estado = EstadoConvocatoria::FINALIZADA;
            $convocatoria->updated_by = $userId;
            $convocatoria->save();

            // 2. Crear al servidor como pre-ingreso (para expediente)
            $servidor = Servidor::create([
                'cedula'    => $ganador->cedula,
                'nombres'   => $ganador->nombres,
                'apellidos' => $ganador->apellidos,

                'puesto_id' => $convocatoria->puesto_id,
                'estado'    => true, // Inicia activo
            ]);

            // 3. Crear el Onboarding vinculado
            Onboarding::create([
                'postulante_id' => $ganador->id,
                'servidor_id'   => $servidor->id,
                'created_by'    => $userId,
            ]);

            // 4. Registrar en Movimientos de Personal (Ingreso)
            MovimientoPersonal::create([
                'servidor_id'    => $servidor->id,
                'tipo'           => 'ingreso',
                'fecha_efectiva' => now()->toDateString(),
                'motivo'         => "Ganador del concurso de méritos y oposición {$convocatoria->codigo}.",
                'created_by'     => $userId,
            ]);

            DB::commit();

            return $ganador;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
