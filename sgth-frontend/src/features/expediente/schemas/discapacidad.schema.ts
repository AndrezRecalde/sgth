import { z } from 'zod/v4'

export const discapacidadSchema = z.object({
  tipo_discapacidad: z.enum([
    'fisica',
    'sensorial',
    'intelectual',
    'psicosocial',
    'visceral',
    'multiple',
  ]),
  porcentaje:            z.number().min(1).max(100),
  numero_carnet_conadis: z.string().min(1, 'El número de carnet es requerido'),
})

export type DiscapacidadFormData = z.infer<typeof discapacidadSchema>
