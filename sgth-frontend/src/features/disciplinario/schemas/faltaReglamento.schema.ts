import { z } from 'zod/v4'

/**
 * Una falta del catálogo del reglamento interno (fase 4.4). Las reglas son las
 * de `GuardarFaltaReglamentoRequest`: al menos una sanción, y la multa de un
 * obrero nunca pasa del 10 % de su remuneración.
 */

/** El tope de la multa de un obrero (Código del Trabajo). */
export const MULTA_MAXIMA_OBRERO = 10

export const faltaReglamentoSchema = z.object({
  articulo: z.string().trim().min(1, 'Indique el artículo o numeral del reglamento').max(100, 'Máximo 100 caracteres'),
  descripcion: z.string().trim().min(5, 'Describa la falta como la redacta el reglamento').max(1000, 'Máximo 1000 caracteres'),
  gravedad: z.string().max(40, 'Máximo 40 caracteres').optional().nullable(),
  admite_amonestacion_verbal: z.boolean(),
  admite_amonestacion_escrita: z.boolean(),
  admite_multa: z.boolean(),
  porcentaje_maximo_multa: z.number()
    .min(0.01, 'Debe ser mayor que cero')
    .max(MULTA_MAXIMA_OBRERO, `La multa de un obrero no pasa del ${MULTA_MAXIMA_OBRERO} % de su remuneración`)
    .optional()
    .nullable(),
  activo: z.boolean(),
})
  .superRefine((v, ctx) => {
    if (!v.admite_amonestacion_verbal && !v.admite_amonestacion_escrita && !v.admite_multa) {
      ctx.addIssue({
        code: 'custom',
        path: ['admite_multa'],
        message: 'Indique al menos una sanción que el reglamento admita para esta falta',
      })
    }

    if (v.admite_multa && v.porcentaje_maximo_multa == null) {
      ctx.addIssue({
        code: 'custom',
        path: ['porcentaje_maximo_multa'],
        message: 'Indique hasta qué porcentaje llega la multa',
      })
    }
  })

export type FaltaReglamentoFormData = z.infer<typeof faltaReglamentoSchema>
