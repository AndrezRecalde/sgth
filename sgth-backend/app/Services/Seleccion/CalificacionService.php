<?php

namespace App\Services\Seleccion;

use App\Enums\EstadoConvocatoria;
use App\Enums\EstadoPostulante;
use App\Exceptions\ReglaNegocioException;
use App\Models\Seleccion\CalificacionPostulante;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\Seleccion\EvaluacionSeleccion;
use App\Models\Seleccion\Postulante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La calificación por criterios de un candidato (2026-10-05).
 *
 * Antes, en el controlador: el criterio no se comprobaba contra la
 * convocatoria ni la opción contra su criterio —se podía puntuar con los de
 * otro concurso—, un criterio repetido sumaba dos veces, el número por encima
 * del máximo se recortaba en silencio, el checklist guardaba una sola opción
 * mientras el total las sumaba todas, y el total salía de lo enviado, no de lo
 * guardado.
 */
final class CalificacionService
{
    /** El puntaje mínimo para aprobar, sobre 100 (decisión de TH: se queda en 70). */
    public const PUNTAJE_APROBATORIO = 70;

    /**
     * Los criterios vigentes de una convocatoria. Los inactivos son de
     * plantillas anteriores de un contenedor express: se conservan por las
     * calificaciones que ya se hicieron con ellos.
     *
     * @return Collection<int, CriterioEvaluacion>
     */
    public static function criteriosVigentes(int $convocatoriaId): Collection
    {
        return CriterioEvaluacion::with('opciones')
            ->where('convocatoria_id', $convocatoriaId)
            ->where('activo', true)
            ->orderBy('seccion')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /** La regla del 100: sin ella, el 70 no significa nada. */
    public static function assertSuman100(Collection $criterios, string $accion): void
    {
        $suma = round((float) $criterios->sum(fn (CriterioEvaluacion $c) => (float) $c->puntaje_maximo), 2);

        if ($suma !== 100.0) {
            throw new ReglaNegocioException(
                "Los criterios de evaluación suman {$suma} puntos y deben sumar 100 para {$accion}."
            );
        }
    }

    /**
     * @param  list<array{criterio_id: int, opcion_id?: ?int, opcion_ids?: ?list<int>, valor_numerico?: ?float, observacion?: ?string}>  $items
     */
    public function guardar(Postulante $postulante, array $items, int $userId): EvaluacionSeleccion
    {
        $convocatoria = $postulante->convocatoria;

        // Solo mientras el concurso recibe calificaciones; un contenedor
        // express está siempre publicado.
        if (! in_array($convocatoria->estado, [EstadoConvocatoria::PUBLICADA, EstadoConvocatoria::EN_EVALUACION], true)) {
            throw new ReglaNegocioException('La convocatoria ya no admite calificaciones.');
        }

        // El puntaje ya decidió la suerte del aspirante y el trámite avanzó.
        // Recalificar a alguien en evaluación médica lo devolvía en silencio
        // a «aprobado» y se perdía el despacho al dispensario.
        if (! $postulante->estado->admiteCalificacion()) {
            throw new ReglaNegocioException(
                'El aspirante ya avanzó a '.$postulante->estado->value.' y su calificación no se puede modificar.'
            );
        }

        $criterios = self::criteriosVigentes($convocatoria->id)->keyBy('id');
        self::assertSuman100($criterios, 'calificar');

        $puntajes = $this->validarYPuntuar($criterios, $items);

        return DB::transaction(function () use ($postulante, $criterios, $items, $puntajes, $userId) {
            foreach ($items as $i => $item) {
                $criterio = $criterios[(int) $item['criterio_id']];
                $esChecklist = $criterio->tipo_input === 'checklist';

                $calificacion = CalificacionPostulante::updateOrCreate(
                    ['postulante_id' => $postulante->id, 'criterio_id' => $criterio->id],
                    [
                        'opcion_id'        => $criterio->tipo_input === 'radio' ? $item['opcion_id'] : null,
                        'valor_numerico'   => $criterio->tipo_input === 'numero' ? $item['valor_numerico'] : null,
                        'puntaje_obtenido' => $puntajes[$i],
                        'observacion'      => $item['observacion'] ?? null,
                        'registrado_por'   => $userId,
                    ]
                );

                $calificacion->opciones()->sync($esChecklist ? array_values(array_unique($item['opcion_ids'] ?? [])) : []);
            }

            // El total sale de lo guardado, no de lo enviado.
            $guardadas = CalificacionPostulante::where('postulante_id', $postulante->id)
                ->whereIn('criterio_id', $criterios->keys())
                ->get();

            $porSeccion = fn (string $seccion) => (float) $guardadas
                ->filter(fn ($c) => $criterios[$c->criterio_id]->seccion === $seccion)
                ->sum('puntaje_obtenido');

            $meritos = $porSeccion('meritos');
            $oposicion = $porSeccion('oposicion');
            $total = $meritos + $oposicion;

            $evaluacion = EvaluacionSeleccion::updateOrCreate(
                ['postulante_id' => $postulante->id],
                [
                    'puntaje_meritos'   => $meritos,
                    'puntaje_oposicion' => $oposicion,
                    'puntaje_total'     => $total,
                    'evaluador_id'      => $userId,
                    'updated_by'        => $userId,
                ]
            );

            $postulante->update([
                'estado' => $total >= self::PUNTAJE_APROBATORIO ? EstadoPostulante::APROBADO : EstadoPostulante::REPROBADO,
            ]);

            return $evaluacion;
        });
    }

    /**
     * Cada error va al campo que lo causó (`calificaciones.N.…`).
     *
     * @return array<int, float> el puntaje de cada ítem, por su índice
     */
    private function validarYPuntuar(Collection $criterios, array $items): array
    {
        $errores = [];
        $puntajes = [];
        $vistos = [];

        foreach ($items as $i => $item) {
            $campo = "calificaciones.{$i}";
            $criterio = $criterios->get((int) $item['criterio_id']);

            if (! $criterio) {
                $errores["{$campo}.criterio_id"] = 'Este criterio no pertenece a la convocatoria o ya no está vigente.';

                continue;
            }

            if (isset($vistos[$criterio->id])) {
                $errores["{$campo}.criterio_id"] = "El criterio «{$criterio->nombre}» está repetido.";

                continue;
            }
            $vistos[$criterio->id] = true;

            $maximo = (float) $criterio->puntaje_maximo;
            $opciones = $criterio->opciones->keyBy('id');

            switch ($criterio->tipo_input) {
                case 'numero':
                    $valor = $item['valor_numerico'] ?? null;
                    if ($valor === null) {
                        $errores["{$campo}.valor_numerico"] = "Ingrese el puntaje de «{$criterio->nombre}».";
                    } elseif ((float) $valor > $maximo) {
                        $errores["{$campo}.valor_numerico"] = "«{$criterio->nombre}» admite hasta {$maximo} puntos.";
                    } else {
                        $puntajes[$i] = (float) $valor;
                    }
                    break;

                case 'radio':
                    $opcion = $opciones->get((int) ($item['opcion_id'] ?? 0));
                    if (! $opcion) {
                        $errores["{$campo}.opcion_id"] = "Elija una opción de «{$criterio->nombre}».";
                    } else {
                        $puntajes[$i] = min((float) $opcion->puntaje, $maximo);
                    }
                    break;

                case 'checklist':
                    // Ninguna marcada es válido: el candidato no cumple ninguna.
                    $ids = array_unique(array_map('intval', $item['opcion_ids'] ?? []));
                    $ajenas = array_diff($ids, $opciones->keys()->all());
                    if ($ajenas) {
                        $errores["{$campo}.opcion_ids"] = "Hay opciones que no son de «{$criterio->nombre}».";
                    } else {
                        // Cada marca suma; el criterio no pasa de su máximo.
                        $puntajes[$i] = min((float) $opciones->only($ids)->sum('puntaje'), $maximo);
                    }
                    break;
            }
        }

        $faltan = $criterios->reject(fn (CriterioEvaluacion $c) => isset($vistos[$c->id]));
        if ($faltan->isNotEmpty()) {
            $errores['calificaciones'] = 'Falta calificar: '.$faltan->pluck('nombre')->join(', ').'.';
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return $puntajes;
    }
}
