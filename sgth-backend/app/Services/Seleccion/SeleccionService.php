<?php

namespace App\Services\Seleccion;

use App\Contracts\Seleccion\SeleccionServiceInterface;
use App\Enums\EstadoConvocatoria;
use App\Enums\EstadoPostulante;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Onboarding;
use App\Models\Seleccion\Postulante;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SeleccionService implements SeleccionServiceInterface
{
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

    /**
     * El cierre del concurso formal (decisión de TH, 2026-10-04). Antes lo
     * hacía «Declarar ganador oficial» (confirmarGanador), que marcaba
     * ganador a cualquiera en evaluación médica —también a un «no apto»— y
     * finalizaba sin crear el expediente ni el ingreso: un concurso formal
     * nunca terminaba en una persona contratada.
     *
     * Ahora cada ganador se incorpora uno por uno, con su dictamen de aptitud
     * (SolicitudCertificacionController::confirmarIncorporacion), y cuando ya
     * no queda ninguno por resolver la convocatoria se finaliza sola. Los que
     * esperaban en la lista de espera quedan como no seleccionados.
     *
     * Un no apto deja una vacante sin cubrir: mientras quede alguien en lista
     * de espera, el concurso sigue abierto para declarar al siguiente
     * (`declararSiguiente`). Sin nadie a quien declarar, se finaliza con los
     * que hubo, o queda desierto si no se incorporó ninguno.
     *
     * Se llama dentro de la transacción de la incorporación o del dictamen.
     */
    public function cerrarConcursoSiCorresponde(int $convocatoriaId, int $userId): ?EstadoConvocatoria
    {
        $convocatoria = Convocatoria::lockForUpdate()->findOrFail($convocatoriaId);

        if ($convocatoria->es_contenedor_permanente
            || $convocatoria->estado !== EstadoConvocatoria::EN_EVALUACION_MEDICA) {
            return null;
        }

        $delConcurso = fn (EstadoPostulante $estado) => Postulante::where('convocatoria_id', $convocatoria->id)
            ->where('estado', $estado->value);

        if ($delConcurso(EstadoPostulante::GANADOR_POTENCIAL)->exists()) {
            return null;
        }

        $incorporados = $delConcurso(EstadoPostulante::INCORPORADO)->count();
        $vacantesCubiertas = $incorporados >= (int) ($convocatoria->vacantes ?? 1);

        if (! $vacantesCubiertas && $delConcurso(EstadoPostulante::LISTA_ESPERA)->exists()) {
            return null;
        }

        $estado = $incorporados > 0 ? EstadoConvocatoria::FINALIZADA : EstadoConvocatoria::DESIERTA;

        $convocatoria->update([
            'estado'     => $estado,
            'updated_by' => $userId,
        ]);

        $delConcurso(EstadoPostulante::LISTA_ESPERA)
            ->update(['estado' => EstadoPostulante::NO_SELECCIONADO->value]);

        return $estado;
    }

    /**
     * Antes el no apto se quedaba en «ganador_potencial» para siempre: no se
     * podía incorporar, nadie ocupaba su lugar y el concurso formal no se
     * cerraba nunca (decisión de TH, 2026-10-04).
     */
    public function descalificarPorNoApto(int $postulanteId, int $userId): void
    {
        $postulante = Postulante::lockForUpdate()->findOrFail($postulanteId);

        if ($postulante->estado !== EstadoPostulante::GANADOR_POTENCIAL) {
            return;
        }

        $postulante->update(['estado' => EstadoPostulante::DESCALIFICADO]);

        $this->cerrarConcursoSiCorresponde($postulante->convocatoria_id, $userId);
    }

    /**
     * El siguiente lo decide el puntaje, no Talento Humano: en un concurso de
     * méritos, saltarse a alguien de la lista de espera no es una opción.
     * A igual puntaje, el que se inscribió primero.
     */
    public function declararSiguiente(int $convocatoriaId, int $userId): Postulante
    {
        return DB::transaction(function () use ($convocatoriaId, $userId) {
            $convocatoria = Convocatoria::with('puesto.cargo')->lockForUpdate()->findOrFail($convocatoriaId);

            if ($convocatoria->es_contenedor_permanente) {
                throw new ReglaNegocioException(
                    'En el reclutamiento express no hay ranking: cada aspirante se envía por separado.'
                );
            }

            if ($convocatoria->estado !== EstadoConvocatoria::EN_EVALUACION_MEDICA) {
                throw new ReglaNegocioException(
                    'Solo se declara al siguiente mientras la convocatoria está en evaluación médica.'
                );
            }

            $ocupadas = Postulante::where('convocatoria_id', $convocatoria->id)
                ->whereIn('estado', [
                    EstadoPostulante::GANADOR_POTENCIAL->value,
                    EstadoPostulante::INCORPORADO->value,
                ])
                ->count();

            if ($ocupadas >= (int) ($convocatoria->vacantes ?? 1)) {
                throw new ReglaNegocioException(
                    'Las vacantes ya están cubiertas o en evaluación médica: no hay lugar para otro candidato.'
                );
            }

            $siguiente = Postulante::with('puesto.cargo')
                ->select('postulantes.*')
                ->join('evaluaciones_seleccion as e', 'e.postulante_id', '=', 'postulantes.id')
                ->where('postulantes.convocatoria_id', $convocatoria->id)
                ->where('postulantes.estado', EstadoPostulante::LISTA_ESPERA->value)
                ->orderByDesc('e.puntaje_total')
                ->orderBy('postulantes.id')
                ->lockForUpdate()
                ->first();

            if (! $siguiente) {
                throw new ReglaNegocioException('No queda nadie en la lista de espera.');
            }

            $siguiente->update(['estado' => EstadoPostulante::GANADOR_POTENCIAL]);
            $this->solicitarCertificacion($convocatoria, $siguiente, $userId);

            return $siguiente->fresh();
        });
    }

    /**
     * Antes esto se hacía con el PATCH de la convocatoria, que aceptaba
     * cualquier estado —también dos que no existen y daban 500— y no dejaba
     * constancia de por qué (2026-10-05).
     */
    public function cerrarSinGanadores(
        int $convocatoriaId, EstadoConvocatoria $estado, string $motivo, int $userId
    ): Convocatoria {
        if (! in_array($estado, [EstadoConvocatoria::DESIERTA, EstadoConvocatoria::CANCELADA], true)) {
            throw new ReglaNegocioException('Un concurso sin ganadores se declara desierto o se cancela.');
        }

        return DB::transaction(function () use ($convocatoriaId, $estado, $motivo, $userId) {
            $convocatoria = Convocatoria::lockForUpdate()->findOrFail($convocatoriaId);

            if ($convocatoria->es_contenedor_permanente) {
                throw new ReglaNegocioException('Los contenedores de reclutamiento express son permanentes: no se cierran.');
            }

            if (! in_array($convocatoria->estado, [EstadoConvocatoria::PUBLICADA, EstadoConvocatoria::EN_EVALUACION], true)) {
                throw new ReglaNegocioException(match (true) {
                    $convocatoria->estado === EstadoConvocatoria::BORRADOR => 'Una convocatoria en borrador no se cierra: se elimina.',
                    $convocatoria->estado === EstadoConvocatoria::EN_EVALUACION_MEDICA => 'Ya hay candidatos en evaluación médica: el concurso se cierra al resolverlos.',
                    default => 'Esta convocatoria ya está cerrada.',
                });
            }

            // Desierto es que nadie alcanzó el puntaje: con aprobados, lo que
            // corresponde es declararlos ganadores. Cancelar sí cabe siempre.
            $aprobados = Postulante::where('convocatoria_id', $convocatoria->id)
                ->where('estado', EstadoPostulante::APROBADO->value)
                ->count();

            if ($estado === EstadoConvocatoria::DESIERTA && $aprobados > 0) {
                throw new ReglaNegocioException(
                    "Hay {$aprobados} candidato(s) aprobado(s): declare ganadores o cancele la convocatoria."
                );
            }

            $convocatoria->update([
                'estado'        => $estado,
                'motivo_cierre' => $motivo,
                'updated_by'    => $userId,
            ]);

            // Los que seguían en carrera quedan fuera; un reprobado sigue
            // reprobado, que es lo que fue.
            Postulante::where('convocatoria_id', $convocatoria->id)
                ->whereIn('estado', [
                    EstadoPostulante::INSCRITO->value,
                    EstadoPostulante::EN_EVALUACION->value,
                    EstadoPostulante::APROBADO->value,
                ])
                ->update(['estado' => EstadoPostulante::NO_SELECCIONADO->value]);

            return $convocatoria;
        });
    }

    /**
     * Una solicitud cancelada dejaba al candidato «en evaluación médica» para
     * siempre: nadie lo iba a evaluar y no se le podía volver a enviar
     * (2026-10-05). En el formal vuelve a la lista de espera, y como conserva
     * su puntaje, «Declarar al siguiente» lo vuelve a elegir si sigue siendo
     * el primero.
     */
    public function devolverPorCancelacion(int $postulanteId): void
    {
        $postulante = Postulante::with('convocatoria')->lockForUpdate()->findOrFail($postulanteId);

        if ($postulante->estado !== EstadoPostulante::GANADOR_POTENCIAL) {
            return;
        }

        $postulante->update([
            'estado' => $postulante->convocatoria->es_contenedor_permanente
                ? EstadoPostulante::APROBADO
                : EstadoPostulante::LISTA_ESPERA,
        ]);
    }

    /**
     * Solo se declaran ganadores en un concurso publicado. Antes bastaba con
     * que no estuviera finalizado ni en evaluación médica: también servía uno
     * en borrador, desierto o cancelado.
     */
    private function assertConcursoAbierto(Convocatoria $convocatoria): void
    {
        if (in_array($convocatoria->estado, [EstadoConvocatoria::PUBLICADA, EstadoConvocatoria::EN_EVALUACION], true)) {
            return;
        }

        throw new ReglaNegocioException(match ($convocatoria->estado) {
            EstadoConvocatoria::BORRADOR => 'La convocatoria está en borrador: publíquela primero.',
            EstadoConvocatoria::EN_EVALUACION_MEDICA => 'Esta convocatoria ya tiene candidatos en evaluación médica.',
            default => 'Esta convocatoria ya está cerrada.',
        });
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
