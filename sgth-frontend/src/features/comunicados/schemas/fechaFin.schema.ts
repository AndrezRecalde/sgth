import { z } from 'zod/v4'

/** Cambiar la fecha de fin de lo publicado: `YYYY-MM-DD`, o vacía para que no venza. */
export const fechaFinSchema = z.object({
  publicado_hasta: z.string(),
})

export type FechaFinFormData = z.infer<typeof fechaFinSchema>
