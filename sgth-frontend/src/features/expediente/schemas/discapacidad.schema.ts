import { z } from 'zod/v4'

export const discapacidadSchema = z.object({
  tipo_discapacidad:     z.string().min(2, 'Requerido'),
  porcentaje:            z.number().min(1).max(100),
  numero_carnet_conadis: z.string().min(1, 'Requerido'),
})

export type DiscapacidadFormData = z.infer<typeof discapacidadSchema>
