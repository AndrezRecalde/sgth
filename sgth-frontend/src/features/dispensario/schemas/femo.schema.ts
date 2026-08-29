import { z } from 'zod/v4'

export const fichaBaseSchema = z.object({
  servidor_id:                   z.number().optional().nullable(),
  postulante_id:                 z.number().optional().nullable(),
  puesto_id:                     z.number().optional().nullable(),
  accidente_trabajo_id:          z.number().optional().nullable(),
  numero_archivo:                z.string().optional().nullable(),
  fecha_evaluacion:              z.string().min(1, 'Requerido'),
  tipo_ficha:                    z.enum(['ingreso','periodica','reintegro','retiro','especial']),
  puesto_trabajo:                z.string().optional().nullable(),
  puesto_trabajo_ciuo:           z.string().optional().nullable(),
  fecha_ingreso_trabajo:         z.string().optional().nullable(),
  fecha_reintegro:               z.string().optional().nullable(),
  fecha_ultimo_dia_laboral:      z.string().optional().nullable(),
  grupo_embarazada:              z.boolean().optional().default(false),
  grupo_discapacidad:            z.boolean().optional().default(false),
  grupo_enfermedad_catastrofica: z.boolean().optional().default(false),
  grupo_adulto_mayor:            z.boolean().optional().default(false),
  porcentaje_discapacidad:       z.string().optional().nullable(),
  /** Mano dominante. Observación clínica del formulario 028. */
  lateralidad:                   z.enum(['derecha','izquierda']).optional().nullable(),
  aptitud:                       z.enum(['apto','apto_con_restricciones','en_observacion','no_apto']),
  restricciones:                 z.string().optional().nullable(),
  observaciones:                 z.string().optional().nullable(),
  enfermedad_actual:             z.string().optional().nullable(),
  /** Nulo = no respondió, que no es lo mismo que respondió que no. */
  autoriza_transfusion:          z.boolean().optional().nullable(),
  tratamiento_hormonal:          z.boolean().optional().nullable(),
  tratamiento_hormonal_cual:     z.string().optional().nullable(),
  recomendaciones:               z.string().optional().nullable(),
  tratamiento:                   z.string().optional().nullable(),
  condicion_relacionada_trabajo: z.boolean().optional().nullable(),
  observacion_retiro:            z.string().optional().nullable(),
  actividad_extralaboral_descripcion: z.string().optional().nullable(),
  actividad_extralaboral_fecha:  z.string().optional().nullable(),
  se_realiza_evaluacion_retiro:  z.boolean().optional().nullable(),
  actividad_fisica_cual:         z.string().optional().nullable(),
  actividad_fisica_tiempo:       z.string().optional().nullable(),
  medicacion_habitual_cual:      z.string().optional().nullable(),
  medicacion_habitual_cantidad:  z.string().optional().nullable(),
})

export const constantesVitalesSchema = z.object({
  temperatura_c:           z.number().optional().nullable(),
  presion_sistolica:       z.number().optional().nullable(),
  presion_diastolica:      z.number().optional().nullable(),
  frecuencia_cardiaca:     z.number().optional().nullable(),
  frecuencia_respiratoria: z.number().optional().nullable(),
  saturacion_oxigeno:      z.number().optional().nullable(),
  peso_kg:                 z.number().optional().nullable(),
  talla_cm:                z.number().optional().nullable(),
  perimetro_abdominal_cm:  z.number().optional().nullable(),
  imc:                     z.number().optional().nullable(),
  glucosa:                 z.number().optional().nullable(),
})

export const antecedenteSchema = z.object({
  tipo:             z.string().min(1),
  descripcion:      z.string().min(3, 'Mínimo 3 caracteres'),
  fecha_aproximada: z.number().optional().nullable(),
})

export const factorRiesgoSchema = z.object({
  categoria:         z.string().min(1),
  factor:            z.string().min(1),
  presente:          z.boolean().default(true),
  medida_preventiva: z.string().optional().nullable(),
  actividad_index:   z.number().optional().nullable(),
})

export const actividadRiesgoSchema = z.object({
  puesto_actividad_id: z.number().optional().nullable(),
  actividad:            z.string().min(1),
  medida_preventiva:    z.string().optional().nullable(),
  orden:                z.number().optional().nullable(),
})

export const diagnosticoFemoSchema = z.object({
  diagnostico_cie10_id: z.number(),
  tipo:                 z.enum(['presuntivo','definitivo']),
  orden:                z.number().min(1).max(6),
})

export const examenSchema = z.object({
  nombre_examen: z.string().min(1),
  resultado:     z.string().optional().nullable(),
  fecha_examen:  z.string().optional().nullable(),
  tipo:          z.enum(['laboratorio','imagen','otro']),
})

export const empleoAnteriorSchema = z.object({
  centro_trabajo:           z.string().min(1),
  actividades_desempenadas: z.string().optional().nullable(),
  /** Columna «TRABAJO: ANTERIOR / ACTUAL» del impreso. */
  es_trabajo_actual:        z.boolean().optional(),
  fecha_inicio:             z.string().optional().nullable(),
  fecha_fin:                z.string().optional().nullable(),
  observaciones:            z.string().optional().nullable(),
  tipo_evento_laboral:      z.enum(['ninguno','incidente','accidente','enfermedad_profesional']),
  calificado_iess:          z.boolean().optional().nullable(),
  fecha_evento:             z.string().optional().nullable(),
  especificar:              z.string().optional().nullable(),
})

export const examenFisicoItemSchema = z.object({
  region:      z.string().min(1),
  item:        z.string().min(1),
  normal:      z.boolean().default(true),
  observacion: z.string().optional().nullable(),
})

export const antecedenteReproductivoSchema = z.object({
  fecha_ultima_menstruacion: z.string().optional().nullable(),
  gestas:                    z.number().optional().nullable(),
  partos:                    z.number().optional().nullable(),
  cesareas:                  z.number().optional().nullable(),
  abortos:                   z.number().optional().nullable(),
  usa_metodo_planificacion:  z.enum(['si','no','no_responde']).optional().nullable(),
  metodo_planificacion_cual: z.string().optional().nullable(),
  examenes_realizados:       z.string().optional().nullable(),
  examenes_tiempo_anios:     z.number().optional().nullable(),
})

export const consumoSustanciaSchema = z.object({
  sustancia:                 z.enum(['tabaco','alcohol','otra']),
  sustancia_otra_detalle:    z.string().optional().nullable(),
  tiempo_consumo_meses:      z.number().optional().nullable(),
  ex_consumidor:             z.boolean().default(false),
  tiempo_abstinencia_meses:  z.number().optional().nullable(),
  no_consume:                z.boolean().default(false),
})

export type FichaBaseForm               = z.infer<typeof fichaBaseSchema>
export type ConstantesVitalesForm       = z.infer<typeof constantesVitalesSchema>
export type AntecedenteForm             = z.infer<typeof antecedenteSchema>
export type FactorRiesgoForm            = z.infer<typeof factorRiesgoSchema>
export type ActividadRiesgoForm         = z.infer<typeof actividadRiesgoSchema>
export type DiagnosticoFemoForm         = z.infer<typeof diagnosticoFemoSchema>
export type ExamenForm                  = z.infer<typeof examenSchema>
export type EmpleoAnteriorForm          = z.infer<typeof empleoAnteriorSchema>
export type ExamenFisicoItemForm        = z.infer<typeof examenFisicoItemSchema>
export type AntecedenteReproductivoForm = z.infer<typeof antecedenteReproductivoSchema>
export type ConsumoSustanciaForm        = z.infer<typeof consumoSustanciaSchema>
