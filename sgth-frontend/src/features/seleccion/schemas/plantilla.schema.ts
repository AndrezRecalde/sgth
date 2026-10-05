import { z } from 'zod/v4'

/** Una plantilla de criterios reutilizable. Antes se validaba a mano con `register(..., { required })`. */
export const plantillaSchema = z.object({
  nombre:        z.string().trim().min(3, 'Mínimo 3 caracteres').max(200, 'Máximo 200 caracteres'),
  descripcion:   z.string().optional().nullable(),
  tipo_contrato: z.string().nullable(),
})

export type PlantillaFormData = z.infer<typeof plantillaSchema>

export const CAMPOS_PLANTILLA = ['nombre', 'descripcion', 'tipo_contrato'] as const
