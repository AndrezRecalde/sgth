import { z } from 'zod/v4'

/** Cambiar la fecha de fin de lo publicado: `YYYY-MM-DD`. Se alarga o se acorta, pero no se quita. */
export const fechaFinSchema = z.object({
  publicado_hasta: z.string().min(1, 'Elija la fecha de fin'),
})

export type FechaFinFormData = z.infer<typeof fechaFinSchema>
