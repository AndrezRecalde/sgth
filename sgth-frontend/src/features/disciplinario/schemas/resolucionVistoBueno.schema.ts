import { z } from 'zod/v4'

/**
 * El PDF de la resolución del Inspector del Trabajo. Lo mismo que valida
 * `AdjuntarResolucionVistoBuenoRequest`: solo PDF, hasta 10 MB.
 */
export const RESOLUCION_MAX_MB = 10
export const RESOLUCION_TIPOS = ['application/pdf']

export const resolucionVistoBuenoSchema = z.object({
  archivo: z.instanceof(File, { error: 'Seleccione el PDF de la resolución' }),
})

export type ResolucionVistoBuenoFormData = z.infer<typeof resolucionVistoBuenoSchema>
