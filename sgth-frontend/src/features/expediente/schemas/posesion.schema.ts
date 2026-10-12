import { z } from 'zod/v4'
import { noEsFutura } from '@/lib/fecha'

/**
 * La posesión del cargo (fase 6.4). El lugar lo pide solo la acción de
 * personal, que lo imprime; el ocasional se presenta a laborar y su registro
 * del contrato no tiene documento. Que no sea anterior al día en que rige lo
 * mira el selector, y también el backend.
 */
export function crearPosesionSchema(pideLugar: boolean) {
  return z.object({
    fecha_posesion: z.string().min(1, 'Indique la fecha').refine(noEsFutura, 'No puede ser una fecha futura'),
    lugar_posesion: z.string().max(150, 'Máximo 150 caracteres').optional().nullable(),
  }).superRefine((d, ctx) => {
    if (pideLugar && !d.lugar_posesion?.trim()) {
      ctx.addIssue({ path: ['lugar_posesion'], code: 'custom', message: 'Indique el lugar de la posesión' })
    }
  })
}

export type PosesionFormData = z.infer<ReturnType<typeof crearPosesionSchema>>
