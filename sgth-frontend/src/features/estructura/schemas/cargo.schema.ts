import { z } from 'zod/v4'

export const cargoSchema = z.object({
  nombre: z.string().min(3, 'Mínimo 3 caracteres'),
  denominacion_generica: z.string().optional(),
  mision: z.string().optional(),
  clasificacion_personal: z.enum([
    'empleado', 'contratado', 'obrero',
  ]),
})

export type CargoFormData = z.infer<typeof cargoSchema>
