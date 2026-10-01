import { z } from 'zod/v4'
import { fromDateValue } from '@/lib/fecha'

/**
 * Apertura del sumario administrativo. Las reglas son las de
 * `StoreSumarioRequest`.
 */

const MOTIVO_MAX = 2000

/**
 * Las fechas viajan como `YYYY-MM-DD`, así que comparar cadenas ordena igual
 * que comparar días. El backend también lo rechaza
 * (`before_or_equal:today`); aquí se avisa antes de gastar una petición.
 */
export function esFechaFutura(fecha: string): boolean {
  return fecha > fromDateValue(new Date())
}

export const sumarioSchema = z.object({
  servidor_id: z.number({ message: 'Seleccione el servidor sumariado' }),
  motivo: z.string()
    .min(5, 'Describa los hechos que motivan la apertura')
    .max(MOTIVO_MAX, `Máximo ${MOTIVO_MAX} caracteres`),
  fecha_apertura: z.string()
    .min(1, 'Indique la fecha de apertura')
    .refine((f) => !esFechaFutura(f), 'El sumario no puede abrirse con una fecha futura'),
})

export type SumarioFormValues = z.infer<typeof sumarioSchema>
