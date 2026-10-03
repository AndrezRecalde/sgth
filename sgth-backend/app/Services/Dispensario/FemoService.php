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
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Postulante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FemoService
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = FichaSaludOcupacional::with([
            'servidor:id,nombre,apellido,cedula',
            'postulante:id,nombres,apellidos,cedula',
            'evaluador:id,usuario_ti,email,servidor_id',
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
            'evaluador:id,usuario_ti,email,servidor_id',
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
            'solicitud:id,ficha_femo_id,estado,tipo_evento,dictamen',
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

    /**
     * Embarazada y lactancia solo aplican a pacientes mujeres.
     *
     * El asistente ya no ofrece esas casillas a un hombre; esto cierra la
     * puerta a quien llame a la API directamente. Con el sexo sin registrar
     * se aceptan, igual que el asistente las muestra: un expediente
     * incompleto no debe hacer perder el dato.
     */
    private function validarGruposPorSexo(array $campos, ?string $genero): void
    {
        if ($genero !== 'masculino') {
            return;
        }

        $errores = [];
        foreach (['grupo_embarazada' => 'embarazada', 'grupo_lactancia' => 'en lactancia'] as $campo => $texto) {
            if (! empty($campos[$campo])) {
                $errores["ficha.{$campo}"] = "Un paciente de sexo masculino no puede registrarse como {$texto}.";
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Registra la ficha FEMO de una solicitud y la enlaza a ella.
     *
     * La ficha nace como borrador de la solicitud en curso: se puede seguir
     * editando hasta que el médico emite el dictamen. Una solicitud tiene una
     * sola ficha; antes, cerrar el modal del dictamen dejaba la ficha suelta y
     * «Continuar FEMO» creaba otra.
     *
     * La persona y el tipo de evaluación los fija la solicitud, no el cliente.
     * Sin `solicitud_id` (solo llamadas internas y pruebas) se respeta lo que
     * venga en la ficha.
     */
    public function registrar(array $datos, int $evaluadorId): FichaSaludOcupacional
    {
        return DB::transaction(function () use ($datos, $evaluadorId) {
            $solicitud = null;
            if (! empty($datos['solicitud_id'])) {
                $solicitud = SolicitudCertificacionMedica::lockForUpdate()
                    ->findOrFail($datos['solicitud_id']);

                if ($solicitud->estado !== 'en_proceso') {
                    throw ValidationException::withMessages([
                        'solicitud_id' => 'La solicitud no está en curso: inicie la evaluación desde la bandeja.',
                    ]);
                }
                if ($solicitud->ficha_femo_id) {
                    throw ValidationException::withMessages([
                        'solicitud_id' => 'Esta solicitud ya tiene su ficha FEMO; ábrala para continuarla.',
                    ]);
                }

                $datos['ficha']['servidor_id'] = $solicitud->servidor_id;
                $datos['ficha']['postulante_id'] = $solicitud->postulante_id;
                $datos['ficha']['tipo_ficha'] = $solicitud->tipo_evento;
            }

            $genero = ! empty($datos['ficha']['servidor_id'])
                ? Servidor::whereKey($datos['ficha']['servidor_id'])->value('genero')
                : Postulante::whereKey($datos['ficha']['postulante_id'] ?? null)->value('genero');
            $this->validarGruposPorSexo($datos['ficha'], $genero);

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

            $solicitud?->update(['ficha_femo_id' => $ficha->id]);

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

            // Solo se edita el borrador de una evaluación en curso. Con el
            // dictamen emitido, la ficha es el respaldo de lo que se certificó:
            // cambiarle la aptitud después dejaba un certificado que ya no
            // coincidía con su ficha.
            $solicitud = SolicitudCertificacionMedica::where('ficha_femo_id', $ficha->id)
                ->lockForUpdate()
                ->first();
            if ($solicitud?->estado !== 'en_proceso') {
                throw ValidationException::withMessages([
                    'ficha' => $solicitud?->estado === 'completada'
                        ? 'La ficha ya tiene el dictamen emitido y no se puede modificar.'
                        : 'Esta ficha no pertenece a una evaluación en curso y no se puede modificar.',
                ]);
            }

            // La ficha es de una persona y no cambia de dueño al editarla: el
            // PATCH ya no declara `servidor_id` ni `postulante_id`, así que no
            // llegan validados. Se quitan también aquí por si otro llamador los
            // manda; dejar los dos en nulo rompía el CHECK `chk_ficha_persona`.
            $campos = $datos['ficha'] ?? [];
            unset($campos['servidor_id'], $campos['postulante_id'], $campos['tipo_ficha']);

            $this->validarGruposPorSexo(
                $campos,
                $ficha->servidor?->genero ?? $ficha->postulante?->genero,
            );

            $ficha->update([
                ...$this->sellarPuesto($campos),
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
                foreach ($datos['antecedentes'] ?? [] as $ant) {
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
                foreach ($datos['diagnosticos'] ?? [] as $diag) {
                    FemoDiagnostico::create([
                        ...$diag, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            if (array_key_exists('examenes', $datos)) {
                $ficha->examenes()->delete();
                foreach ($datos['examenes'] ?? [] as $exam) {
                    FemoExamen::create([
                        ...$exam, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            if (array_key_exists('empleos_anteriores', $datos)) {
                $ficha->empleosAnteriores()->delete();
                foreach ($datos['empleos_anteriores'] ?? [] as $emp) {
                    FemoEmpleoAnterior::create([
                        ...$emp, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            if (array_key_exists('examen_fisico', $datos)) {
                $ficha->examenFisico()->delete();
                foreach ($datos['examen_fisico'] ?? [] as $item) {
                    FemoExamenFisico::create([
                        ...$item, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            if (array_key_exists('antecedente_reproductivo', $datos)) {
                // Llega nulo cuando el médico vació el bloque: se borra, no se
                // actualiza (`updateOrCreate` con nulo es un TypeError).
                if (empty($datos['antecedente_reproductivo'])) {
                    $ficha->antecedenteReproductivo()->delete();
                } else {
                    $ficha->antecedenteReproductivo()->updateOrCreate(
                        ['ficha_id' => $ficha->id],
                        $datos['antecedente_reproductivo']
                    );
                }
            }

            if (array_key_exists('consumo_sustancias', $datos)) {
                $ficha->consumoSustancias()->delete();
                foreach ($datos['consumo_sustancias'] ?? [] as $consumo) {
                    FemoConsumoSustancia::create([
                        ...$consumo, 'ficha_id' => $ficha->id,
                    ]);
                }
            }

            return $this->obtener($ficha->id);
        });
    }
}
