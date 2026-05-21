import { z } from 'zod/v4'

export const unidadSchema = z.object({
  nombre:             z.string().min(3, 'Mínimo 3 caracteres'),
  codigo:             z.string().optional(),
  tipo_unidad_id:     z.number({ error: 'Seleccione un tipo de unidad' }),
  unidad_padre_id:    z.number().optional().nullable(),
  mision:             z.string().optional(),
  presupuesto_total:  z.number().optional().nullable(),
})

export type UnidadFormData = z.infer<typeof unidadSchema>
