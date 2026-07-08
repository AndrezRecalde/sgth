import { z } from 'zod/v4'

export const fichaBaseSchema = z.object({
  servidor_id:                   z.number({ error: 'Seleccione un servidor' }),
  fecha_evaluacion:              z.string().min(1, 'Requerido'),
  tipo_ficha:                    z.enum(['ingreso','periodica','reintegro','retiro','especial']),
  puesto_trabajo:                z.string().optional().nullable(),
  puesto_trabajo_ciuo:           z.string().optional().nullable(),
  fecha_ingreso_trabajo:         z.string().optional().nullable(),
  grupo_embarazada:              z.boolean().optional().default(false),
  grupo_discapacidad:            z.boolean().optional().default(false),
  porcentaje_discapacidad:       z.string().optional().nullable(),
  aptitud:                       z.enum(['apto','apto_con_restricciones','en_observacion','no_apto']),
  restricciones:                 z.string().optional().nullable(),
  observaciones:                 z.string().optional().nullable(),
  enfermedad_actual:             z.string().optional().nullable(),
  recomendaciones:               z.string().optional().nullable(),
  tratamiento:                   z.string().optional().nullable(),
  condicion_relacionada_trabajo: z.boolean().optional().nullable(),
  observacion_retiro:            z.string().optional().nullable(),
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
  fecha_inicio:             z.string().optional().nullable(),
  fecha_fin:                z.string().optional().nullable(),
  observaciones:            z.string().optional().nullable(),
})

export type FichaBaseForm         = z.infer<typeof fichaBaseSchema>
export type ConstantesVitalesForm = z.infer<typeof constantesVitalesSchema>
export type AntecedenteForm       = z.infer<typeof antecedenteSchema>
export type FactorRiesgoForm      = z.infer<typeof factorRiesgoSchema>
export type DiagnosticoFemoForm   = z.infer<typeof diagnosticoFemoSchema>
export type ExamenForm            = z.infer<typeof examenSchema>
export type EmpleoAnteriorForm    = z.infer<typeof empleoAnteriorSchema>
