<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\AptitudMedica;
use App\Enums\CategoriaRiesgoLaboral;
use App\Enums\RegionExamenFisico;
use App\Enums\TipoAntecedenteFemo;
use App\Enums\TipoEventoLaboral;
use App\Enums\TipoExamenFemo;
use App\Enums\TipoFichaFemo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFichaSaludOcupacionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ficha' => ['required', 'array'],
            'ficha.servidor_id' => ['nullable', 'required_without:ficha.postulante_id', 'integer', 'exists:servidores,id'],
            'ficha.postulante_id' => ['nullable', 'required_without:ficha.servidor_id', 'integer', 'exists:postulantes,id'],
            'ficha.accidente_trabajo_id' => ['nullable', 'integer', 'exists:accidentes_trabajo,id'],
            'ficha.numero_archivo' => ['nullable', 'string', 'max:50'],
            'ficha.fecha_evaluacion' => ['required', 'date'],
            'ficha.tipo_ficha' => ['required', Rule::enum(TipoFichaFemo::class)],
            'ficha.puesto_trabajo' => ['nullable', 'string', 'max:200'],
            'ficha.puesto_trabajo_ciuo' => ['nullable', 'string', 'max:20'],
            'ficha.fecha_ingreso_trabajo' => ['nullable', 'date'],
            'ficha.grupo_embarazada' => ['nullable', 'boolean'],
            'ficha.grupo_discapacidad' => ['nullable', 'boolean'],
            'ficha.porcentaje_discapacidad' => ['nullable', 'string', 'max:10'],
            'ficha.aptitud' => ['required', Rule::enum(AptitudMedica::class)],
            'ficha.restricciones' => ['nullable', 'string'],
            'ficha.observaciones' => ['nullable', 'string'],
            'ficha.enfermedad_actual' => ['nullable', 'string'],
            'ficha.recomendaciones' => ['nullable', 'string'],
            'ficha.tratamiento' => ['nullable', 'string'],
            'ficha.condicion_relacionada_trabajo' => ['nullable', 'boolean'],
            'ficha.observacion_retiro' => ['nullable', 'string'],
            'ficha.actividad_extralaboral_descripcion' => ['nullable', 'string'],
            'ficha.actividad_extralaboral_fecha' => ['nullable', 'date'],
            'ficha.se_realiza_evaluacion_retiro' => ['nullable', 'boolean'],
            'ficha.actividad_fisica_cual' => ['nullable', 'string', 'max:200'],
            'ficha.actividad_fisica_tiempo' => ['nullable', 'string', 'max:50'],
            'ficha.medicacion_habitual_cual' => ['nullable', 'string', 'max:200'],
            'ficha.medicacion_habitual_cantidad' => ['nullable', 'string', 'max:100'],

            'constantes_vitales' => ['nullable', 'array'],
            'constantes_vitales.temperatura_c' => ['nullable', 'numeric'],
            'constantes_vitales.presion_sistolica' => ['nullable', 'integer'],
            'constantes_vitales.presion_diastolica' => ['nullable', 'integer'],
            'constantes_vitales.frecuencia_cardiaca' => ['nullable', 'integer'],
            'constantes_vitales.frecuencia_respiratoria' => ['nullable', 'integer'],
            'constantes_vitales.saturacion_oxigeno' => ['nullable', 'numeric'],
            'constantes_vitales.peso_kg' => ['nullable', 'numeric'],
            'constantes_vitales.talla_cm' => ['nullable', 'numeric'],
            'constantes_vitales.imc' => ['nullable', 'numeric'],
            'constantes_vitales.glucosa' => ['nullable', 'numeric'],

            'antecedentes' => ['nullable', 'array'],
            'antecedentes.*.tipo' => ['required', Rule::enum(TipoAntecedenteFemo::class)],
            'antecedentes.*.descripcion' => ['required', 'string'],
            'antecedentes.*.fecha_aproximada' => ['nullable', 'integer'],

            'actividades' => ['nullable', 'array'],
            'actividades.*.puesto_actividad_id' => ['nullable', 'integer', 'exists:puesto_actividades,id'],
            'actividades.*.actividad' => ['required', 'string', 'max:200'],
            'actividades.*.medida_preventiva' => ['nullable', 'string'],
            'actividades.*.orden' => ['nullable', 'integer', 'min:1'],

            'factores_riesgo' => ['nullable', 'array'],
            'factores_riesgo.*.categoria' => ['required', Rule::enum(CategoriaRiesgoLaboral::class)],
            'factores_riesgo.*.factor' => ['required', 'string', 'max:200'],
            'factores_riesgo.*.presente' => ['nullable', 'boolean'],
            'factores_riesgo.*.medida_preventiva' => ['nullable', 'string', 'max:500'],
            'factores_riesgo.*.actividad_index' => ['nullable', 'integer', 'min:0'],

            'diagnosticos' => ['nullable', 'array', 'max:6'],
            'diagnosticos.*.diagnostico_cie10_id' => ['required', 'integer', 'exists:diagnosticos_cie10,id'],
            'diagnosticos.*.tipo' => ['required', Rule::in(['presuntivo', 'definitivo'])],
            'diagnosticos.*.orden' => ['required', 'integer', 'min:1', 'max:6'],

            'examenes' => ['nullable', 'array'],
            'examenes.*.nombre_examen' => ['required', 'string', 'max:200'],
            'examenes.*.tipo' => ['required', Rule::enum(TipoExamenFemo::class)],
            'examenes.*.resultado' => ['nullable', 'string'],
            'examenes.*.fecha_examen' => ['nullable', 'date'],

            'empleos_anteriores' => ['nullable', 'array'],
            'empleos_anteriores.*.centro_trabajo' => ['required', 'string', 'max:200'],
            'empleos_anteriores.*.actividades_desempenadas' => ['nullable', 'string'],
            'empleos_anteriores.*.fecha_inicio' => ['nullable', 'date'],
            'empleos_anteriores.*.fecha_fin' => ['nullable', 'date'],
            'empleos_anteriores.*.observaciones' => ['nullable', 'string'],
            'empleos_anteriores.*.tipo_evento_laboral' => ['nullable', Rule::enum(TipoEventoLaboral::class)],
            'empleos_anteriores.*.calificado_iess' => ['nullable', 'boolean'],
            'empleos_anteriores.*.fecha_evento' => ['nullable', 'date'],
            'empleos_anteriores.*.especificar' => ['nullable', 'string'],

            'examen_fisico' => ['nullable', 'array'],
            'examen_fisico.*.region' => ['required', Rule::enum(RegionExamenFisico::class)],
            'examen_fisico.*.item' => ['required', 'string', 'max:100'],
            'examen_fisico.*.normal' => ['nullable', 'boolean'],
            'examen_fisico.*.observacion' => ['nullable', 'string', 'max:500'],

            'antecedente_reproductivo' => ['nullable', 'array'],
            'antecedente_reproductivo.fecha_ultima_menstruacion' => ['nullable', 'date'],
            'antecedente_reproductivo.gestas' => ['nullable', 'integer', 'min:0'],
            'antecedente_reproductivo.partos' => ['nullable', 'integer', 'min:0'],
            'antecedente_reproductivo.cesareas' => ['nullable', 'integer', 'min:0'],
            'antecedente_reproductivo.abortos' => ['nullable', 'integer', 'min:0'],
            'antecedente_reproductivo.usa_metodo_planificacion' => ['nullable', Rule::in(['si', 'no', 'no_responde'])],
            'antecedente_reproductivo.metodo_planificacion_cual' => ['nullable', 'string', 'max:200'],
            'antecedente_reproductivo.examenes_realizados' => ['nullable', 'string', 'max:300'],
            'antecedente_reproductivo.examenes_tiempo_anios' => ['nullable', 'integer', 'min:0'],

            'consumo_sustancias' => ['nullable', 'array'],
            'consumo_sustancias.*.sustancia' => ['required', Rule::in(['tabaco', 'alcohol', 'otra'])],
            'consumo_sustancias.*.sustancia_otra_detalle' => ['nullable', 'string', 'max:100'],
            'consumo_sustancias.*.tiempo_consumo_meses' => ['nullable', 'integer', 'min:0'],
            'consumo_sustancias.*.ex_consumidor' => ['nullable', 'boolean'],
            'consumo_sustancias.*.tiempo_abstinencia_meses' => ['nullable', 'integer', 'min:0'],
            'consumo_sustancias.*.no_consume' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'exists' => 'El valor seleccionado para :attribute no es válido.',
            'diagnosticos.max' => 'Máximo 6 diagnósticos.',
        ];
    }
}
