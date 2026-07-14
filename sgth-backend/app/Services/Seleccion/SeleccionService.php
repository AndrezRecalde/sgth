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
use App\Models\Dispensario\SolicitudCertificacionMedica;
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
        $convocatoria = Convocatoria::with('puesto.cargo')
            ->findOrFail($convocatoriaId);
        $ganador = Postulante::where('convocatoria_id', $convocatoriaId)
            ->findOrFail($postulanteGanadorId);

        if (in_array($convocatoria->estado, [
            EstadoConvocatoria::FINALIZADA,
            EstadoConvocatoria::EN_EVALUACION_MEDICA,
        ])) {
            throw new ReglaNegocioException(
                'Esta convocatoria ya tiene un candidato en evaluación médica o fue finalizada.'
            );
        }

        if ($ganador->estado->value !== EstadoPostulante::APROBADO->value) {
            throw new ReglaNegocioException(
                'El postulante debe estar aprobado (puntaje >= 70) para ser enviado al dispensario.'
            );
        }

        DB::beginTransaction();
        try {
            // 1. Cambiar convocatoria a EN_EVALUACION_MEDICA
            //    (NO finalizar todavía — esperar dictamen)
            $convocatoria->estado = EstadoConvocatoria::EN_EVALUACION_MEDICA;
            $convocatoria->updated_by = $userId;
            $convocatoria->save();

            // 2. Marcar ganador como GANADOR_POTENCIAL
            //    (NO crear expediente todavía —
            //    esperar confirmación médica)
            $ganador->update([
                'estado' => EstadoPostulante::GANADOR_POTENCIAL,
            ]);

            // 3. Marcar aprobados restantes como lista_espera
            Postulante::where('convocatoria_id', $convocatoriaId)
                ->where('id', '!=', $ganador->id)
                ->where('estado', EstadoPostulante::APROBADO->value)
                ->update(['estado' => 'lista_espera']);

            // 4. Generar solicitud de certificación médica
            //    con datos del CANDIDATO (no del servidor)
            $nombreCompleto = trim(implode(' ', array_filter([
                $ganador->nombres,
                $ganador->segundo_nombre,
                $ganador->apellidos,
                $ganador->segundo_apellido,
            ])));

            SolicitudCertificacionMedica::create([
                'tipo_evento'      => 'ingreso',
                'origen'           => 'reclutamiento',
                'postulante_id'    => $ganador->id,
                'convocatoria_id'  => $convocatoria->id,
                'cedula_paciente'  => $ganador->cedula,
                'nombres_paciente' => $nombreCompleto,
                'correo_paciente'  => $ganador->correo,
                'puesto_solicitado'=> $convocatoria->puesto?->cargo?->nombre,
                'solicitado_por'   => $userId,
                'estado'           => 'pendiente',
                'fecha_limite'     => now()->addDays(7)->toDateString(),
            ]);

            DB::commit();
            return $ganador->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
