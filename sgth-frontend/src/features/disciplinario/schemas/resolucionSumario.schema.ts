import { z } from 'zod/v4'

/**
 * Resolución de un sumario administrativo: la falta que se da por probada y la
 * sanción que se impone. Las reglas son las de `ResolverSumarioRequest` y las
 * del Art. 43 de la LOSEP.
 *
 * El porcentaje y los días no son opcionales «a veces»: lo son según la
 * sanción elegida, y el servidor guarda la columna que corresponde. Una multa
 * sin porcentaje o una suspensión sin días dejan un acto que no dice cuánto,
 * así que se exigen aquí y no solo en el backend.
 */

/** Tope del Art. 43 de la LOSEP: la multa no pasa del 10% de la remuneración. */
export const MULTA_MAX = 10

/** Tope del Art. 43 de la LOSEP: la suspensión no pasa de 30 días. */
export const SUSPENSION_MAX_DIAS = 30

const OBSERVACIONES_MAX = 1000

export const resolucionSumarioSchema = z.object({
  tipo_falta: z.enum(['leve', 'grave', 'muy_grave'], {
    message: 'Indique la gravedad de la falta',
  }),
  tipo_sancion: z.enum(
    ['amonestacion_verbal', 'amonestacion_escrita', 'multa', 'suspension', 'destitucion'],
    { message: 'Indique la sanción que se impone' },
  ),
  porcentaje_multa: z.number()
    .min(0.01, 'Debe ser mayor que cero')
    .max(MULTA_MAX, `La LOSEP no admite más del ${MULTA_MAX}% de la remuneración`)
    .optional(),
  dias_suspension: z.number()
    .int('Los días no se parten por la mitad')
    .min(1, 'Debe ser al menos un día')
    .max(SUSPENSION_MAX_DIAS, `La LOSEP no admite más de ${SUSPENSION_MAX_DIAS} días`)
    .optional(),
  fecha_efectiva: z.string().min(1, 'Indique desde cuándo surte efecto la sanción'),
  observaciones: z.string().max(OBSERVACIONES_MAX, `Máximo ${OBSERVACIONES_MAX} caracteres`),
})
  .superRefine((v, ctx) => {
    if (v.tipo_sancion === 'multa' && v.porcentaje_multa === undefined) {
      ctx.addIssue({
        code: 'custom',
        path: ['porcentaje_multa'],
        message: 'Una multa necesita su porcentaje',
      })
    }

    if (v.tipo_sancion === 'suspension' && v.dias_suspension === undefined) {
      ctx.addIssue({
        code: 'custom',
        path: ['dias_suspension'],
        message: 'Una suspensión necesita sus días',
      })
    }
  })

export type ResolucionSumarioFormData = z.infer<typeof resolucionSumarioSchema>
