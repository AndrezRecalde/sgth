import { z } from 'zod/v4'

/**
 * El reintegro que se prepara desde «Ausencias y reemplazos» (fase 2.4): cuándo
 * vuelve el servidor y la explicación que lleva el documento. Que la fecha
 * caiga dentro de la ausencia lo valida el backend, que la conoce; el selector
 * ya no deja elegir fuera de ese rango.
 */
export const reintegroSchema = z.object({
  fecha_regreso: z.string().min(1, 'Indique la fecha de regreso'),
  descripcion: z
    .string()
    .trim()
    .min(5, 'Explique el reintegro: es el texto del documento')
    .max(1000, 'Máximo 1000 caracteres'),
})

export type ReintegroFormData = z.infer<typeof reintegroSchema>
