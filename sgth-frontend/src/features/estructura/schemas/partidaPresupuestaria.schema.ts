import { z } from 'zod/v4'

export const partidaPresupuestariaSchema = z.object({
  codigo: z.string()
    .min(3, 'Mínimo 3 caracteres')
    .max(20, 'Máximo 20 caracteres'),
  descripcion: z.string()
    .min(3, 'Mínimo 3 caracteres')
    .max(200, 'Máximo 200 caracteres'),
  grupo_gasto: z.string().max(100).optional(),
  activo: z.boolean(),
  // Disponibilidad presupuestaria verificada (Art. 105 LOSEP): no se asume,
  // el área presupuestaria la marca explícitamente.
  disponible: z.boolean(),
})

export type PartidaPresupuestariaFormData = z.infer<typeof partidaPresupuestariaSchema>
