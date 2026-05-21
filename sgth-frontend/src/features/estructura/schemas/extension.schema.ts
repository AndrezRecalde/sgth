import { z } from 'zod/v4'

export const extensionSchema = z.object({
  unidad_administrativa_id: z.number({ error: 'Seleccione una unidad' }),
  numero_extension:         z.string().min(1).max(10, 'Máximo 10 caracteres'),
  responsable:              z.string().min(2, 'Mínimo 2 caracteres'),
  descripcion:              z.string().optional(),
  estado:                   z.boolean().default(true),
})

export type ExtensionFormData = z.infer<typeof extensionSchema>
