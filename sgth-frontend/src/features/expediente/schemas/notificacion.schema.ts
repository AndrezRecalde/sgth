import { z } from 'zod/v4'

/**
 * Notificar la acción al servidor (Reglamento a la LOSEP, Art. 22; diseño,
 * 6.4; fase 6.3a). Por el portal, nada más que pedir: el documento aparece en
 * su portal y le llega al correo. En persona, si la recibió o se negó, y la
 * negativa con la razón y un testigo. Las reglas son las de
 * `TransicionarMovimientoRequest`.
 */
export const notificacionSchema = z.object({
  medio: z.enum(['portal', 'personal']),
  resultado: z.enum(['recibida', 'negada']).nullable().optional(),
  razon: z.string().max(500, 'Máximo 500 caracteres').nullable().optional(),
  testigo_nombre: z.string().max(150, 'Máximo 150 caracteres').nullable().optional(),
  testigo_cedula: z.string().max(13, 'Máximo 13 caracteres').nullable().optional(),
}).superRefine((d, ctx) => {
  if (d.medio !== 'personal') return

  if (!d.resultado) {
    ctx.addIssue({ path: ['resultado'], code: 'custom', message: 'Indique si la recibió o si se negó' })
  }
  if (d.resultado === 'negada') {
    if ((d.razon?.trim().length ?? 0) < 5) {
      ctx.addIssue({ path: ['razon'], code: 'custom', message: 'Indique por qué se negó a recibirla' })
    }
    if (!d.testigo_nombre?.trim()) {
      ctx.addIssue({ path: ['testigo_nombre'], code: 'custom', message: 'Indique el nombre del testigo' })
    }
    if ((d.testigo_cedula?.trim().length ?? 0) < 6) {
      ctx.addIssue({ path: ['testigo_cedula'], code: 'custom', message: 'Indique la cédula del testigo' })
    }
  }
})

export type NotificacionFormData = z.infer<typeof notificacionSchema>
