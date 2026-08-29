<?php

namespace App\Services\Dispensario;

use App\Catalogos\FactoresRiesgoMsp;
use App\Models\Dispensario\FemoAntecedente;
use App\Models\Dispensario\FemoConstantesVitales;
use App\Models\Dispensario\FemoConsumoSustancia;
use App\Models\Dispensario\FemoDiagnostico;
use App\Models\Dispensario\FemoEmpleoAnterior;
use App\Models\Dispensario\FemoExamen;
use App\Models\Dispensario\FemoExamenFisico;
use App\Models\Dispensario\FemoFactorRiesgo;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Estructura\Puesto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class FemoService
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = FichaSaludOcupacional::with([
            'servidor:id,nombre,apellido,cedula',
            'postulante:id,nombres,apellidos,cedula',
            'evaluador:id,usuario_ti,servidor_id',
            'evaluador.servidor:id,nombre,apellido',
        ])->orderBy('fecha_evaluacion', 'desc');

        if (! empty($filtros['servidor_id'])) {
            $query->where('servidor_id', $filtros['servidor_id']);
        }

        if (! empty($filtros['tipo_ficha'])) {
            $query->where('tipo_ficha', $filtros['tipo_ficha']);
        }

        if (! empty($filtros['aptitud'])) {
            $query->where('aptitud', $filtros['aptitud']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate(
                'fecha_evaluacion', '>=', $filtros['fecha_desde']
            );
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate(
                'fecha_evaluacion', '<=', $filtros['fecha_hasta']
            );
        }

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    public function obtener(int $id): FichaSaludOcupacional
    {
        return FichaSaludOcupacional::with([
            'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula,fecha_nacimiento,genero,tipo_sangre,tiene_discapacidad,tiene_enfermedad_catastrofica',
            'servidor.historiaClinica:id,servidor_id,numero_historia',
            'postulante:id,cedula,nombres,segundo_nombre,apellidos,segundo_apellido,fecha_nacimiento,genero,tipo_sangre',
            'evaluador:id,usuario_ti,servidor_id',
            // El código médico va en la sección O de la ficha impresa.
            'evaluador.servidor:id,nombre,apellido,cedula,codigo_medico',
            'puesto.cargo:id,nombre,codigo_ciuo',
            'puesto.unidadAdministrativa:id,nombre',
            'constantesVitales',
            'antecedentes',
            'factoresRiesgo',
            'actividades.factoresRiesgo',
            'diagnosticos.diagnostico',
            'examenes',
            'empleosAnteriores',
            'examenFisico',
            'antecedenteReproductivo',
            'consumoSustancias',
        ])->findOrFail($id);
    }

    /**
     * Sella el nombre del puesto y su código CIUO en la ficha.
     *
     * El cliente manda `puesto_id`; el nombre y el CIUO los resuelve el
     * servidor y no se aceptan del formulario. Son dos cosas distintas: el
     * vínculo apunta a la estructura viva, y el texto es una fotografía del día
     * de la evaluación, porque un cargo puede renombrarse después.
     *
     * Si no llega `puesto_id` se respeta lo que venga escrito a mano: hay
     * evaluaciones —un candidato externo sin puesto asignado todavía— donde el
     * único dato disponible es el que teclea el médico.
     */
    private function sellarPuesto(array $ficha): array
    {
        if (empty($ficha['puesto_id'])) {
            return $ficha;
        }

        $puesto = Puesto::with('cargo')->find($ficha['puesto_id']);
        if (! $puesto?->cargo) {
            return $ficha;
        }

        $ficha['puesto_trabajo'] = $puesto->cargo->nombre;
        $ficha['puesto_trabajo_ciuo'] = $puesto->cargo->codigo_ciuo;

        return $ficha;
    }

    public function registrar(array $datos, int $evaluadorId): FichaSaludOcupacional
    {
        return DB::transaction(function () use ($datos, $evaluadorId) {
            $ficha = FichaSaludOcupacional::create([
                ...$this->sellarPuesto($datos['ficha']),
                'evaluador_id' => $evaluadorId,
                'estado' => true,
                'created_by' => $evaluadorId,
            ]);

            if (! empty($datos['constantes_vitales'])) {
                FemoConstantesVitales::create([
                    ...$datos['constantes_vitales'],
                    'ficha_id' => $ficha->id,
                ]);
            }

            foreach ($datos['antecedentes'] ?? [] as $ant) {
                FemoAntecedente::create([
                    ...$ant, 'ficha_id' => $ficha->id,
                ]);
            }

            $mapaActividades = [];
            foreach ($datos['actividades'] ?? [] as $i => $act) {
                $actividad = $ficha->actividades()->create($act);
                $mapaActividades[$i] = $actividad->id;
            }

            foreach ($datos['factores_riesgo'] ?? [] as $factor) {
                $actividadIndex = $factor['actividad_index'] ?? null;
                unset($factor['actividad_index']);
                FemoFactorRiesgo::create([
                    ...$factor,
                    // La subcategoría no la manda el cliente: se deduce del
                    // catálogo del MSP para que no pueda contradecir al factor.
                    'subcategoria' => FactoresRiesgoMsp::subcategoriaDe(
                        $factor['categoria'],
                        $factor['factor'],
                    ),
                    'ficha_id' => $ficha->id,
                    'ficha_actividad_id' => $actividadIndex !== null ? ($mapaActividades[$actividadIndex] ?? null) : null,
                ]);
            }

            foreach ($datos['diagnosticos'] ?? [] as $diag) {
                FemoDiagnostico::create([
                    ...$diag, 'ficha_id' => $ficha->id,
                ]);
            }

            foreach ($datos['examenes'] ?? [] as $exam) {
                FemoExamen::create([
                    ...$exam, 'ficha_id' => $ficha->id,
                ]);
            }

            foreach ($datos['empleos_anteriores'] ?? [] as $emp) {
                FemoEmpleoAnterior::create([
                    ...$emp, 'ficha_id' => $ficha->id,
                ]);
            }

            foreach ($datos['examen_fisico'] ?? [] as $item) {
                FemoExamenFisico::create([
                    ...$item, 'ficha_id' => $ficha->id,
                ]);
            }

            if (! empty($datos['antecedente_reproductivo'])) {
                $ficha->antecedenteReproductivo()->create($datos['antecedente_reproductivo']);
            }

            foreach ($datos['consumo_sustancias'] ?? [] as $consumo) {
                FemoConsumoSustancia::create([
                    ...$consumo, 'ficha_id' => $ficha->id,
                ]);
            }

            return $this->obtener($ficha->id);
        });
    }

    public function actualizar(
        int $id,
        array $datos,
        int $usuarioId
    ): FichaSaludOcupacional {
        return DB::transaction(function () use ($id, $datos, $usuarioId) {
            $ficha = FichaSaludOcupacional::findOrFail($id);
            $ficha->update([
                ...$this->sellarPuesto($datos['ficha']),
                'updated_by' => $usuarioId,
            ]);

            if (! empty($datos['constantes_vitales'])) {
                $ficha->constantesVitales()->updateOrCreate(
                    ['ficha_id' => $ficha->id],
                    $datos['constantes_vitales']
                );
            }

            if (array_key_exists('antecedentes', $datos)) {
                $ficha->antecedentes()->delete();
                foreach ($datos['antecedentes'] as $ant) {
                    FemoAntecedente::create([
                        ...$ant, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            if (array_key_exists('actividades', $datos) || array_key_exists('factores_riesgo', $datos)) {
                $ficha->factoresRiesgo()->delete();
                $ficha->actividades()->delete();

                $mapaActividades = [];
                foreach ($datos['actividades'] ?? [] as $i => $act) {
                    $actividad = $ficha->actividades()->create($act);
                    $mapaActividades[$i] = $actividad->id;
                }

                foreach ($datos['factores_riesgo'] ?? [] as $factor) {
                    $actividadIndex = $factor['actividad_index'] ?? null;
                    unset($factor['actividad_index']);
                    FemoFactorRiesgo::create([
                        ...$factor,
                        'subcategoria' => FactoresRiesgoMsp::subcategoriaDe(
                            $factor['categoria'],
                            $factor['factor'],
                        ),
                        'ficha_id' => $ficha->id,
                        'ficha_actividad_id' => $actividadIndex !== null ? ($mapaActividades[$actividadIndex] ?? null) : null,
                    ]);
                }
            }

            if (array_key_exists('diagnosticos', $datos)) {
                $ficha->diagnosticos()->delete();
                foreach ($datos['diagnosticos'] as $diag) {
                    FemoDiagnostico::create([
                        ...$diag, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            if (array_key_exists('examenes', $datos)) {
                $ficha->examenes()->delete();
                foreach ($datos['examenes'] as $exam) {
                    FemoExamen::create([
                        ...$exam, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            if (array_key_exists('empleos_anteriores', $datos)) {
                $ficha->empleosAnteriores()->delete();
                foreach ($datos['empleos_anteriores'] as $emp) {
                    FemoEmpleoAnterior::create([
                        ...$emp, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            if (array_key_exists('examen_fisico', $datos)) {
                $ficha->examenFisico()->delete();
                foreach ($datos['examen_fisico'] as $item) {
                    FemoExamenFisico::create([
                        ...$item, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            if (array_key_exists('antecedente_reproductivo', $datos)) {
                $ficha->antecedenteReproductivo()->updateOrCreate(
                    ['ficha_id' => $ficha->id],
                    $datos['antecedente_reproductivo']
                );
            }

            if (array_key_exists('consumo_sustancias', $datos)) {
                $ficha->consumoSustancias()->delete();
                foreach ($datos['consumo_sustancias'] as $consumo) {
                    FemoConsumoSustancia::create([
                        ...$consumo, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            return $this->obtener($ficha->id);
        });
    }
}
