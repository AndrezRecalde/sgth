import { z } from 'zod/v4'

/** El tipo de Sirha7 lo elige siempre quien aprueba (decisión del 2026-10-07). */
export const aprobarSirha7Schema = z.object({
  leave_id: z.number({ error: 'Elija el tipo de permiso con que se registra en Sirha7' }).min(1),
})

export type AprobarSirha7FormData = z.infer<typeof aprobarSirha7Schema>
