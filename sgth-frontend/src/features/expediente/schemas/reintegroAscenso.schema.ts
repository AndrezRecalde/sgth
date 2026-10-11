import { z } from 'zod/v4'

/**
 * El reintegro del ascendido que no superó la prueba (fase 3.3): cuándo vuelve
 * a su puesto anterior, el informe de la evaluación —la ley lo condiciona a
 * ella (LOSEP 17 b.5)— y la explicación del documento. Que la fecha caiga
 * dentro de la prueba lo valida el backend; el selector ya no deja salir de
 * ese rango.
 */
export const reintegroAscensoSchema = z.object({
  fecha_regreso: z.string().min(1, 'Indique la fecha de regreso'),
  informe_uath_numero: z
    .string()
    .trim()
    .min(1, 'Indique el número del informe de la evaluación')
    .max(100, 'Máximo 100 caracteres'),
  descripcion: z
    .string()
    .trim()
    .min(5, 'Explique el reintegro: es el texto del documento')
    .max(1000, 'Máximo 1000 caracteres'),
})

export type ReintegroAscensoFormData = z.infer<typeof reintegroAscensoSchema>
