import { z } from 'zod/v4'
import { AYUDA_PERIODO, PATRON_PERIODO } from '../constants/periodo'

/**
 * La carga manual de horas trabajadas del período: el denominador de los tres
 * índices del CD 513.
 *
 * El formulario no validaba nada. El período era un `TextInput` suelto, así que
 * «2026-13» o «el año pasado» salían hacia el servidor y volvían como un 422
 * que solo se veía en una notificación, sin señalar el campo. La expresión es
 * la misma que usa `PeriodoSso` en el backend.
 */
export const horasTrabajadasSchema = z.object({
  periodo: z
    .string()
    .min(1, 'El período es obligatorio')
    .regex(PATRON_PERIODO, AYUDA_PERIODO),
  unidad_administrativa_id: z.number().nullable(),
  total_horas: z
    .number({ error: 'Indique el total de horas' })
    .int('Las horas van en números enteros')
    .min(1, 'Mínimo 1 hora'),
})

export type HorasTrabajadasFormData = z.infer<typeof horasTrabajadasSchema>
