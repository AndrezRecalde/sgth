import { z } from 'zod/v4'

/** El puesto de contrato al que pasa un ocasional o un profesional (P9, PR 4). */
export const reubicarContratoSchema = z.object({
  puesto_destino_id: z.number({ error: 'Elija el puesto de contrato' }),
})

export type ReubicarContratoFormData = z.infer<typeof reubicarContratoSchema>
