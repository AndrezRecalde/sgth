import { z } from 'zod/v4'
import type { FaltaReglamentoInterno } from '@/types/api'
import { sancionesDeLaFalta } from '../utils/etiquetas'
import { MULTA_MAXIMA_OBRERO } from './faltaReglamento.schema'

/**
 * La resolución del procedimiento del reglamento interno de un obrero (fase
 * 4.4): la falta del catálogo que se da por probada y la sanción que el
 * reglamento le da. La multa no pasa del tope de la falta, que nunca supera el
 * 10 %. Lo mismo comprueba DisciplinarioService::assertSancionDelReglamento().
 */
export function crearResolucionReglamentoSchema(faltas: FaltaReglamentoInterno[]) {
  return z.object({
    falta_reglamento_id: z.number({ message: 'Indique la falta del reglamento interno' }),
    tipo_sancion: z.enum(['amonestacion_verbal', 'amonestacion_escrita', 'multa'], {
      message: 'Indique la sanción que se impone',
    }),
    porcentaje_multa: z.number()
      .min(0.01, 'Debe ser mayor que cero')
      .max(MULTA_MAXIMA_OBRERO, `La multa de un obrero no pasa del ${MULTA_MAXIMA_OBRERO} %`)
      .optional(),
    fecha_efectiva: z.string().min(1, 'Indique desde cuándo surte efecto la sanción'),
    observaciones: z.string().max(1000, 'Máximo 1000 caracteres'),
  })
    .superRefine((v, ctx) => {
      const falta = faltas.find((f) => f.id === v.falta_reglamento_id)
      if (!falta) return

      if (!sancionesDeLaFalta(falta).includes(v.tipo_sancion)) {
        ctx.addIssue({
          code: 'custom',
          path: ['tipo_sancion'],
          message: `El ${falta.articulo} no admite esa sanción`,
        })
      }

      if (v.tipo_sancion === 'multa') {
        if (v.porcentaje_multa === undefined) {
          ctx.addIssue({ code: 'custom', path: ['porcentaje_multa'], message: 'Una multa necesita su porcentaje' })
        } else if (falta.porcentaje_maximo_multa != null && v.porcentaje_multa > Number(falta.porcentaje_maximo_multa)) {
          ctx.addIssue({
            code: 'custom',
            path: ['porcentaje_multa'],
            message: `El ${falta.articulo} admite una multa de hasta el ${Number(falta.porcentaje_maximo_multa)} %`,
          })
        }
      }
    })
}

export type ResolucionReglamentoFormData = z.infer<ReturnType<typeof crearResolucionReglamentoSchema>>
