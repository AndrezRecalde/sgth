import { z } from 'zod/v4'

export const puestoSchema = z.object({
  nombre:                    z.string().min(3, 'Mínimo 3 caracteres'),
  unidad_administrativa_id:  z.number({ error: 'Seleccione una unidad' }),
  codigo:                    z.string().optional(),
  nivel:                     z.string().optional(),
  remuneracion:              z.number().optional().nullable(),
})

export type PuestoFormData = z.infer<typeof puestoSchema>
