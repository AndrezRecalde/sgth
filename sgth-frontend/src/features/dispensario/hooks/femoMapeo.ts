import type {
  ActividadRiesgoForm, FactorRiesgoForm, FichaBaseForm,
} from '../schemas/femo.schema'
import type { CrearFemoData, FichaSaludOcupacional } from '../services/femoService'

/*
| Cómo se pasa de la ficha que devuelve el API al estado del asistente, y de
| vuelta. Funciones puras, aparte del hook que las usa.
*/

/**
 * Los campos de la ficha para enviar.
 *
 * Se envía todo lo que el asistente tiene, no una lista escrita a mano: con
 * la lista, nueve campos que el médico llenaba (lateralidad, transfusión,
 * tratamiento hormonal…) nunca llegaban al servidor y nadie lo notó. Lo que el
 * backend no declara lo descarta su validación.
 */
export function fichaAPayload(
  datos: Partial<FichaBaseForm>,
  fechaEvaluacion: string,
  tipoFicha: FichaBaseForm['tipo_ficha'],
): CrearFemoData['ficha'] {
  return {
    ...datos,
    fecha_evaluacion:              fechaEvaluacion,
    tipo_ficha:                    tipoFicha,
    aptitud:                       datos.aptitud ?? null,
    grupo_embarazada:              datos.grupo_embarazada ?? false,
    grupo_discapacidad:            datos.grupo_discapacidad ?? false,
    grupo_enfermedad_catastrofica: datos.grupo_enfermedad_catastrofica ?? false,
    grupo_lactancia:               datos.grupo_lactancia ?? false,
    grupo_adulto_mayor:            datos.grupo_adulto_mayor ?? false,
  }
}

/** La sección A–N de una ficha guardada, para retomarla en el asistente. */
export function fichaAFormulario(ficha: FichaSaludOcupacional): Partial<FichaBaseForm> {
  return {
    servidor_id:                   ficha.servidor_id ?? null,
    postulante_id:                 ficha.postulante_id ?? null,
    accidente_trabajo_id:          ficha.accidente_trabajo_id ?? null,
    numero_archivo:                ficha.numero_archivo ?? null,
    fecha_evaluacion:              ficha.fecha_evaluacion,
    tipo_ficha:                    ficha.tipo_ficha as FichaBaseForm['tipo_ficha'],
    puesto_id:                     ficha.puesto_id ?? null,
    puesto_trabajo:                ficha.puesto_trabajo ?? null,
    puesto_trabajo_ciuo:           ficha.puesto_trabajo_ciuo ?? null,
    fecha_ingreso_trabajo:         ficha.fecha_ingreso_trabajo ?? null,
    fecha_reintegro:               ficha.fecha_reintegro ?? null,
    fecha_ultimo_dia_laboral:      ficha.fecha_ultimo_dia_laboral ?? null,
    grupo_embarazada:              ficha.grupo_embarazada ?? false,
    grupo_discapacidad:            ficha.grupo_discapacidad ?? false,
    grupo_enfermedad_catastrofica: ficha.grupo_enfermedad_catastrofica ?? false,
    grupo_lactancia:               ficha.grupo_lactancia ?? false,
    grupo_adulto_mayor:            ficha.grupo_adulto_mayor ?? false,
    porcentaje_discapacidad:       ficha.porcentaje_discapacidad ?? null,
    lateralidad:                   ficha.lateralidad ?? null,
    aptitud:                       (ficha.aptitud ?? null) as FichaBaseForm['aptitud'],
    restricciones:                 ficha.restricciones ?? null,
    observaciones:                 ficha.observaciones ?? null,
    enfermedad_actual:             ficha.enfermedad_actual ?? null,
    autoriza_transfusion:          ficha.autoriza_transfusion ?? null,
    tratamiento_hormonal:          ficha.tratamiento_hormonal ?? null,
    tratamiento_hormonal_cual:     ficha.tratamiento_hormonal_cual ?? null,
    recomendaciones:               ficha.recomendaciones ?? null,
    tratamiento:                   ficha.tratamiento ?? null,
    condicion_relacionada_trabajo: ficha.condicion_relacionada_trabajo ?? null,
    observacion_retiro:            ficha.observacion_retiro ?? null,
    actividad_extralaboral_descripcion: ficha.actividad_extralaboral_descripcion ?? null,
    actividad_extralaboral_fecha:  ficha.actividad_extralaboral_fecha ?? null,
    se_realiza_evaluacion_retiro:  ficha.se_realiza_evaluacion_retiro ?? null,
    actividad_fisica_cual:         ficha.actividad_fisica_cual ?? null,
    actividad_fisica_tiempo:       ficha.actividad_fisica_tiempo ?? null,
    medicacion_habitual_cual:      ficha.medicacion_habitual_cual ?? null,
    medicacion_habitual_cantidad:  ficha.medicacion_habitual_cantidad ?? null,
    observacion_antecedentes:      ficha.observacion_antecedentes ?? null,
    observacion_examen_fisico:     ficha.observacion_examen_fisico ?? null,
    observacion_examenes:          ficha.observacion_examenes ?? null,
  }
}

/**
 * Solo las medidas de la sección E: la fila trae además `id`, `ficha_id` y sus
 * fechas, que no son constantes vitales.
 */
export function constantesDe(ficha: FichaSaludOcupacional): Record<string, number | null> {
  const cv = ficha.constantes_vitales ?? {}
  return {
    temperatura_c:           cv.temperatura_c ?? null,
    presion_sistolica:       cv.presion_sistolica ?? null,
    presion_diastolica:      cv.presion_diastolica ?? null,
    frecuencia_cardiaca:     cv.frecuencia_cardiaca ?? null,
    frecuencia_respiratoria: cv.frecuencia_respiratoria ?? null,
    saturacion_oxigeno:      cv.saturacion_oxigeno ?? null,
    peso_kg:                 cv.peso_kg ?? null,
    talla_cm:                cv.talla_cm ?? null,
    perimetro_abdominal_cm:  cv.perimetro_abdominal_cm ?? null,
    imc:                     cv.imc ?? null,
    glucosa:                 cv.glucosa ?? null,
  }
}

/**
 * La matriz de la sección G: las actividades en su orden, y cada factor con el
 * índice de su actividad (así lo espera el backend al guardar). Los factores
 * sin actividad —fichas anteriores a la matriz— se conservan sin índice.
 */
export function riesgosDe(ficha: FichaSaludOcupacional): {
  actividades: ActividadRiesgoForm[]
  factores:    FactorRiesgoForm[]
} {
  const actividades = ficha.actividades ?? []

  const conIndice: FactorRiesgoForm[] = actividades.flatMap((a, index) =>
    (a.factores_riesgo ?? []).map(f => ({
      categoria: f.categoria,
      factor: f.factor,
      presente: f.presente,
      medida_preventiva: f.medida_preventiva ?? null,
      actividad_index: index,
    })))

  const huerfanos: FactorRiesgoForm[] = (ficha.factores_riesgo ?? [])
    .filter(f => !f.ficha_actividad_id)
    .map(f => ({
      categoria: f.categoria,
      factor: f.factor,
      presente: f.presente,
      medida_preventiva: f.medida_preventiva ?? null,
    }))

  return {
    actividades: actividades.map(a => ({
      puesto_actividad_id: a.puesto_actividad_id ?? null,
      actividad: a.actividad,
      medida_preventiva: a.medida_preventiva ?? null,
      orden: a.orden ?? null,
    })),
    factores: [...conIndice, ...huerfanos],
  }
}
