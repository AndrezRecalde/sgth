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
use Illuminate\Support\Collection;
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

    public function declararGanadores(int $convocatoriaId, array $postulanteIds, int $userId): Collection
    {
        $convocatoria = Convocatoria::with('puesto.cargo')->findOrFail($convocatoriaId);

        $ganadores = Postulante::with('puesto.cargo')
            ->where('convocatoria_id', $convocatoriaId)
            ->whereIn('id', $postulanteIds)
            ->get();

        if ($ganadores->count() !== count(array_unique($postulanteIds))) {
            throw new ReglaNegocioException(
                'Alguno de los postulantes indicados no pertenece a esta convocatoria.'
            );
        }

        $esContenedor = (bool) $convocatoria->es_contenedor_permanente;

        // Primero lo estructural: si el concurso ya cerró, decirlo así. Al
        // declarar ganadores los demás aprobados pasan a lista de espera, de
        // modo que un segundo intento fallaría por "no está aprobado" —
        // consecuencia del cierre, no la causa, y un mensaje que despista.
        if (!$esContenedor) {
            $this->assertConcursoAbierto($convocatoria);
            $this->assertCabenEnLasVacantes($convocatoria, $ganadores->count());
        }

        $noAprobados = $ganadores->filter(
            fn (Postulante $p) => $p->estado->value !== EstadoPostulante::APROBADO->value
        );

        if ($noAprobados->isNotEmpty()) {
            throw new ReglaNegocioException(
                'Todos los seleccionados deben estar aprobados (puntaje >= 70) para ser enviados al dispensario. '
                    .'No cumplen: '.$noAprobados->pluck('cedula')->join(', ').'.'
            );
        }

        return DB::transaction(function () use ($convocatoria, $ganadores, $userId, $esContenedor) {
            // Un contenedor express es permanente y sus aspirantes no compiten
            // entre sí: despachar a uno no cierra la modalidad ni manda a los
            // demás a lista de espera.
            if (!$esContenedor) {
                $convocatoria->update([
                    'estado'     => EstadoConvocatoria::EN_EVALUACION_MEDICA,
                    'updated_by' => $userId,
                ]);

                Postulante::where('convocatoria_id', $convocatoria->id)
                    ->whereNotIn('id', $ganadores->pluck('id'))
                    ->where('estado', EstadoPostulante::APROBADO->value)
                    ->update(['estado' => EstadoPostulante::LISTA_ESPERA->value]);
            }

            foreach ($ganadores as $ganador) {
                // No se crea expediente todavía: primero el dictamen médico.
                $ganador->update(['estado' => EstadoPostulante::GANADOR_POTENCIAL]);

                $this->solicitarCertificacion($convocatoria, $ganador, $userId);
            }

            return $ganadores->map->fresh();
        });
    }

    private function assertConcursoAbierto(Convocatoria $convocatoria): void
    {
        if (in_array($convocatoria->estado, [
            EstadoConvocatoria::FINALIZADA,
            EstadoConvocatoria::EN_EVALUACION_MEDICA,
        ], true)) {
            throw new ReglaNegocioException(
                'Esta convocatoria ya tiene candidatos en evaluación médica o fue finalizada.'
            );
        }
    }

    /**
     * No se puede declarar más ganadores que vacantes convocadas: es el número
     * que se publicó y el que respalda presupuestariamente los ingresos.
     */
    private function assertCabenEnLasVacantes(Convocatoria $convocatoria, int $cantidad): void
    {
        $vacantes = (int) ($convocatoria->vacantes ?? 1);

        if ($cantidad > $vacantes) {
            throw new ReglaNegocioException(
                "La convocatoria tiene {$vacantes} vacante(s) y se intentan declarar {$cantidad} ganador(es)."
            );
        }
    }

    /**
     * La solicitud lleva los datos del CANDIDATO, no de un servidor: todavía no
     * existe expediente. El puesto sale del aspirante en los contenedores
     * express y de la convocatoria en un concurso formal.
     */
    private function solicitarCertificacion(
        Convocatoria $convocatoria,
        Postulante $ganador,
        int $userId
    ): void {
        $nombreCompleto = trim(implode(' ', array_filter([
            $ganador->nombres,
            $ganador->segundo_nombre,
            $ganador->apellidos,
            $ganador->segundo_apellido,
        ])));

        SolicitudCertificacionMedica::create([
            'tipo_evento'       => 'ingreso',
            'origen'            => 'reclutamiento',
            'postulante_id'     => $ganador->id,
            'convocatoria_id'   => $convocatoria->id,
            'cedula_paciente'   => $ganador->cedula,
            'nombres_paciente'  => $nombreCompleto,
            'correo_paciente'   => $ganador->correo,
            'puesto_solicitado' => $ganador->puestoEfectivo()?->cargo?->nombre,
            'solicitado_por'    => $userId,
            'estado'            => 'pendiente',
            'fecha_limite'      => now()->addDays(7)->toDateString(),
        ]);
    }
}
