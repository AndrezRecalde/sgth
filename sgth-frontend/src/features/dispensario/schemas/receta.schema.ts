import { z } from 'zod/v4'

export const itemRecetaSchema = z.object({
  inventario_medicina_id: z.number({ error: 'Seleccione la medicina' }),
  nombre_medicina:        z.string(),
  cantidad_prescrita:     z.number().min(1, 'Mínimo 1'),
  dosis:                  z.string().min(1, 'Requerido'),
  frecuencia:             z.string().min(1, 'Requerido'),
  duracion:               z.string().min(1, 'Requerido'),
  observaciones:          z.string().optional().nullable(),
})

export const recetaSchema = z.object({
  indicaciones_generales: z.string().optional().nullable(),
  items: z.array(itemRecetaSchema).min(1, 'Agregue al menos un medicamento'),
})

export type RecetaFormData = z.infer<typeof recetaSchema>
export type ItemRecetaFormData = z.infer<typeof itemRecetaSchema>
