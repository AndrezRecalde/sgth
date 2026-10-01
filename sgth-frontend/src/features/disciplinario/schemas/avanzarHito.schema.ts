import { z } from 'zod/v4'

/**
 * La fecha del hito procesal al que avanza el sumario.
 *
 * Es un solo campo, pero su obligatoriedad depende del hito: la notificación y
 * el informe son hechos que ya ocurrieron y llevan su fecha; el término del
 * período de prueba se puede dejar en blanco para que el servidor cuente los 5
 * días hábiles desde la notificación, que es donde vive la tabla de feriados.
 */
export function hitoSumarioSchema(obligatoria: boolean) {
  return z.object({
    fecha: z.string(),
  }).superRefine((v, ctx) => {
    if (obligatoria && !v.fecha) {
      ctx.addIssue({
        code: 'custom',
        path: ['fecha'],
        message: 'Indique la fecha del hito',
      })
    }
  })
}

export type HitoSumarioFormData = z.infer<ReturnType<typeof hitoSumarioSchema>>
