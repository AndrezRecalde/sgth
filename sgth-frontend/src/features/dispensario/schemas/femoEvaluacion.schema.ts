import { z } from 'zod/v4'

/** Lo que el médico evalúa en la ficha FEMO: factores y actividades de riesgo del puesto, examen físico, exámenes complementarios y diagnósticos. */

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

export const examenFisicoItemSchema = z.object({
  region:      z.string().min(1),
  item:        z.string().min(1),
  normal:      z.boolean().default(true),
  observacion: z.string().optional().nullable(),
})

export const examenSchema = z.object({
  nombre_examen: z.string().min(1),
  resultado:     z.string().optional().nullable(),
  fecha_examen:  z.string().optional().nullable(),
  tipo:          z.enum(['laboratorio','imagen','otro']),
})

export const diagnosticoFemoSchema = z.object({
  diagnostico_cie10_id: z.number(),
  tipo:                 z.enum(['presuntivo','definitivo']),
  orden:                z.number().min(1).max(6),
  /** Solo para mostrar el código y la descripción; el servidor no lo lee. */
  diagnostico:          z.object({ codigo: z.string(), descripcion: z.string() }).optional(),
})

export type FactorRiesgoForm = z.infer<typeof factorRiesgoSchema>
export type ActividadRiesgoForm = z.infer<typeof actividadRiesgoSchema>
export type ExamenFisicoItemForm = z.infer<typeof examenFisicoItemSchema>
export type ExamenForm = z.infer<typeof examenSchema>
export type DiagnosticoFemoForm = z.infer<typeof diagnosticoFemoSchema>
