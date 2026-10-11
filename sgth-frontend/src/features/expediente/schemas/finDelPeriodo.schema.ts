import { z } from 'zod/v4'

/**
 * El fin del período de una autoridad que no lo tiene (fase 4.1). Que no pase
 * de cuatro años desde el inicio lo valida el backend.
 */
export const finDelPeriodoSchema = z.object({
  fecha_fin: z.string().min(1, 'Indique cuándo termina el período'),
})

export type FinDelPeriodoFormData = z.infer<typeof finDelPeriodoSchema>
