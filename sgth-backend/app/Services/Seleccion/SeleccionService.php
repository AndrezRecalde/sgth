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

        if ($convocatoria->estado === EstadoConvocatoria::FINALIZADA) {
            throw new ReglaNegocioException('Esta convocatoria ya fue finalizada previamente.');
        }

        if ($ganador->estado->value !== EstadoPostulante::APROBADO->value) {
            throw new ReglaNegocioException('El postulante debe estar aprobado (puntaje >= 70) para ganar el concurso.');
        }

        DB::beginTransaction();
        try {
            // 1. Finalizar la convocatoria
            $convocatoria->estado = EstadoConvocatoria::FINALIZADA;
            $convocatoria->updated_by = $userId;
            $convocatoria->save();

            // 2. Crear al servidor como pre-ingreso
            $servidor = Servidor::create([
                'cedula'                  => $ganador->cedula,
                'nombre'                  => $ganador->nombres,
                'segundo_nombre'          => $ganador->segundo_nombre,
                'apellido'                => $ganador->apellidos,
                'segundo_apellido'        => $ganador->segundo_apellido,
                'genero'                  => $ganador->genero,
                'estado_civil'            => $ganador->estado_civil,
                'fecha_nacimiento'        => $ganador->fecha_nacimiento?->toDateString(),
                'tipo_sangre'             => $ganador->tipo_sangre,
                'correo_personal'         => $ganador->correo,
                'telefono_celular'        => $ganador->telefono,
                'provincia_nacimiento_id' => $ganador->provincia_nacimiento_id,
                'canton_nacimiento_id'    => $ganador->canton_nacimiento_id,
                'puesto_id'               => $convocatoria->puesto_id,
                'estado'                  => true,
            ]);

            // 3. Crear el Onboarding vinculado
            Onboarding::create([
                'postulante_id' => $ganador->id,
                'servidor_id'   => $servidor->id,
                'created_by'    => $userId,
            ]);

            // 4. Registrar Movimiento de Personal (Ingreso)
            MovimientoPersonal::create([
                'servidor_id'    => $servidor->id,
                'tipo'           => 'ingreso',
                'fecha_efectiva' => now()->toDateString(),
                'motivo'         => "Ganador del concurso de méritos y oposición {$convocatoria->codigo}.",
                'created_by'     => $userId,
            ]);

            // 5. Generar solicitud de certificación médica
            //    de ingreso en el Dispensario
            $nombreCompleto = trim(implode(' ', array_filter([
                $ganador->nombres,
                $ganador->segundo_nombre,
                $ganador->apellidos,
                $ganador->segundo_apellido,
            ])));

            SolicitudCertificacionMedica::create([
                'tipo_evento'      => 'ingreso',
                'origen'           => 'reclutamiento',
                'servidor_id'      => $servidor->id,
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

            // 6. Marcar al ganador como seleccionado
            $ganador->update(['estado' => EstadoPostulante::SELECCIONADO]);

            // 7. Marcar resto como no seleccionados
            Postulante::where('convocatoria_id', $convocatoriaId)
                ->where('id', '!=', $ganador->id)
                ->where('estado', EstadoPostulante::APROBADO)
                ->update(['estado' => 'lista_espera']);

            DB::commit();

            return $ganador->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
