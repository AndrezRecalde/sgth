<?php

namespace App\Http\Requests\Dispensario;

use App\Catalogos\FactoresRiesgoMsp;
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
    use ValidaCoherenciaFemo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Toda ficha nace de una solicitud de Talento Humano: de ella salen
            // la persona y el tipo de evaluación (ver FemoService::registrar).
            'solicitud_id' => ['required', 'integer', 'exists:solicitudes_certificacion_medica,id'],
            'ficha' => ['required', 'array'],
            'ficha.servidor_id' => ['nullable', 'integer', 'exists:servidores,id'],
            'ficha.postulante_id' => ['nullable', 'integer', 'exists:postulantes,id'],
            'ficha.puesto_id' => ['nullable', 'integer', 'exists:puestos,id'],
            'ficha.accidente_trabajo_id' => ['nullable', 'integer', 'exists:accidentes_trabajo,id'],
            'ficha.numero_archivo' => ['nullable', 'string', 'max:50'],
            'ficha.fecha_evaluacion' => ['required', 'date', 'before_or_equal:today'],
            'ficha.tipo_ficha' => ['nullable', Rule::enum(TipoFichaFemo::class)],
            'ficha.puesto_trabajo' => ['nullable', 'string', 'max:200'],
            'ficha.puesto_trabajo_ciuo' => ['nullable', 'string', 'max:20'],
            'ficha.fecha_ingreso_trabajo' => ['nullable', 'date'],
            'ficha.fecha_reintegro' => ['nullable', 'date'],
            'ficha.fecha_ultimo_dia_laboral' => ['nullable', 'date'],
            'ficha.grupo_embarazada' => ['nullable', 'boolean'],
            'ficha.grupo_discapacidad' => ['nullable', 'boolean'],
            'ficha.grupo_enfermedad_catastrofica' => ['nullable', 'boolean'],
            'ficha.grupo_lactancia' => ['nullable', 'boolean'],
            'ficha.grupo_adulto_mayor' => ['nullable', 'boolean'],
            'ficha.porcentaje_discapacidad' => ['nullable', 'string', 'max:10'],
            'ficha.lateralidad' => ['nullable', Rule::in(['derecha', 'izquierda'])],
            // Nula mientras la ficha es un borrador; se exige al emitir el
            // dictamen (SolicitudCertificacionController::completar).
            'ficha.aptitud' => ['nullable', Rule::enum(AptitudMedica::class)],
            'ficha.restricciones' => ['nullable', 'string'],
            'ficha.observaciones' => ['nullable', 'string'],
            'ficha.enfermedad_actual' => ['nullable', 'string'],
            'ficha.autoriza_transfusion' => ['nullable', 'boolean'],
            'ficha.tratamiento_hormonal' => ['nullable', 'boolean'],
            'ficha.tratamiento_hormonal_cual' => ['nullable', 'string', 'max:200'],
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

            // Sin constantes vitales: las tomó Enfermería en el triaje y el
            // servidor las copia de ahí (FemoService::registrar). Antes las
            // mandaba el navegador, sin rangos, y el IMC no se recalculaba.

            'antecedentes' => ['nullable', 'array'],
            'antecedentes.*.tipo' => ['required', Rule::enum(TipoAntecedenteFemo::class)],
            'antecedentes.*.descripcion' => ['required', 'string'],
            'antecedentes.*.fecha_aproximada' => ['nullable', 'integer'],

            // El impreso tiene siete columnas de actividades.
            'actividades' => ['nullable', 'array', 'max:7'],
            'actividades.*.puesto_actividad_id' => ['nullable', 'integer', 'exists:puesto_actividades,id'],
            'actividades.*.actividad' => ['required', 'string', 'max:200'],
            'actividades.*.medida_preventiva' => ['nullable', 'string'],
            'actividades.*.orden' => ['nullable', 'integer', 'min:1'],

            'factores_riesgo' => ['nullable', 'array'],
            'factores_riesgo.*.categoria' => ['required', Rule::enum(CategoriaRiesgoLaboral::class)],
            // El factor debe existir en el catálogo del MSP. Un nombre libre
            // rompe la fidelidad del PDF y descuadra los indicadores de SSO.
            'factores_riesgo.*.factor' => ['required', 'string', Rule::in(FactoresRiesgoMsp::todosLosFactores())],
            'factores_riesgo.*.presente' => ['nullable', 'boolean'],
            'factores_riesgo.*.medida_preventiva' => ['nullable', 'string', 'max:500'],
            'factores_riesgo.*.actividad_index' => ['nullable', 'integer', 'min:0'],

            'diagnosticos' => ['nullable', 'array', 'max:6'],
            'diagnosticos.*.diagnostico_cie10_id' => ['required', 'integer', 'distinct', 'exists:diagnosticos_cie10,id'],
            'diagnosticos.*.tipo' => ['required', Rule::in(['presuntivo', 'definitivo'])],
            'diagnosticos.*.orden' => ['required', 'integer', 'distinct', 'min:1', 'max:6'],

            'examenes' => ['nullable', 'array'],
            'examenes.*.nombre_examen' => ['required', 'string', 'max:200'],
            'examenes.*.tipo' => ['required', Rule::enum(TipoExamenFemo::class)],
            'examenes.*.resultado' => ['nullable', 'string'],
            'examenes.*.fecha_examen' => ['nullable', 'date'],

            'empleos_anteriores' => ['nullable', 'array'],
            'empleos_anteriores.*.centro_trabajo' => ['required', 'string', 'max:200'],
            'empleos_anteriores.*.actividades_desempenadas' => ['nullable', 'string'],
            'empleos_anteriores.*.es_trabajo_actual' => ['nullable', 'boolean'],
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
            'actividades.max' => 'El formulario admite hasta 7 actividades.',
            'diagnosticos.*.diagnostico_cie10_id.distinct' => 'Ese diagnóstico ya está en la lista.',
            'diagnosticos.*.orden.distinct' => 'Dos diagnósticos no pueden tener el mismo número.',
            'ficha.fecha_evaluacion.before_or_equal' => 'La fecha de atención no puede ser futura.',
            // Sin esto el médico vería literalmente «validation.in».
            'factores_riesgo.*.factor.in' => 'Ese factor de riesgo no existe en el formulario del MSP.',
        ];
    }
}
