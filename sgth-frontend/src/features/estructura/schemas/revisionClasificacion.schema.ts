import { z } from 'zod/v4'

/**
 * La revisión de la clasificación (LOSEP Arts. 61 y 62; fase 7.1b): las reglas
 * de `RevisionDeClasificacionRequest`. Lo que pide cada causal y si hace falta
 * el dictamen lo dice la vista previa del backend.
 */
export const revisionClasificacionSchema = z.object({
  causal: z.enum(['revalorizacion', 'reclasificacion', 'ubicacion']),
  puestos: z.array(z.object({
    puesto_id: z.number(),
    grupo_ocupacional_id: z.number().nullable().optional(),
    cargo_id: z.number().nullable().optional(),
  })).min(1, 'Agregue al menos un puesto'),
  documento_origen: z.string().trim().min(1, 'Indique la resolución que la aprueba').max(150, 'Máximo 150 caracteres'),
  dictamen_presupuestario_ref: z.string().trim().max(100, 'Máximo 100 caracteres').optional(),
  descripcion: z.string().trim().min(10, 'Explique la revisión en al menos 10 caracteres').max(1000, 'Máximo 1000 caracteres'),
  fecha_efectiva: z.string().min(1, 'Indique la fecha en que rige'),
})

export type RevisionClasificacionFormData = z.infer<typeof revisionClasificacionSchema>
