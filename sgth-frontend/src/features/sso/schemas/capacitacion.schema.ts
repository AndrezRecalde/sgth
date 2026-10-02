import { z } from 'zod/v4'

export const capacitacionSchema = z.object({
  tema: z.string().min(3, 'Mínimo 3 caracteres').max(200),
  fecha: z.string().min(1, 'La fecha es obligatoria'),
  /**
   * Medias horas: el backend valida `numeric|min:0.5`, no enteros. Una charla
   * de hora y media es lo normal, y el total de horas del período —uno de los
   * cuatro índices proactivos— sale de sumar esta columna.
   */
  duracion_horas: z
    .number({ error: 'Indique la duración en horas' })
    .min(0.5, 'Mínimo media hora')
    .max(999, 'Revise la duración'),
  instructor: z.string().min(3, 'Mínimo 3 caracteres').max(150),
  lugar: z.string().max(200).optional(),
  estado: z.boolean(),
})

export type CapacitacionFormData = z.infer<typeof capacitacionSchema>
