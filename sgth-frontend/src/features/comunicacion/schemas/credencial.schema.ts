import { z } from 'zod/v4'

/** Revocar una credencial: el motivo siempre; el detalle, si es «otro» (6.3). */
export const revocarCredencialSchema = z.object({
  motivo: z.enum(['perdida', 'robo', 'datos_erroneos', 'otro'], { error: 'Elija el motivo' }),
  detalle: z.string().trim().max(500, 'Hasta 500 caracteres'),
}).refine((v) => v.motivo !== 'otro' || v.detalle.length >= 5, {
  path: ['detalle'],
  message: 'Diga por qué se revoca la credencial',
})

export type RevocarCredencialFormData = z.infer<typeof revocarCredencialSchema>

/** La tarjeta impresa volvió a Comunicación (6.5): no puede ser en el futuro. */
export const devolucionTarjetaSchema = z.object({
  fecha: z.string({ error: 'Elija la fecha' }).min(1, 'Elija la fecha'),
  nota: z.string().trim().max(500, 'Hasta 500 caracteres'),
})

export type DevolucionTarjetaFormData = z.infer<typeof devolucionTarjetaSchema>

/** Imprimir: el formato siempre; la unidad, cuando se imprime por unidad. */
export const imprimirCredencialesSchema = z.object({
  formato: z.enum(['cr80', 'a4']),
  unidad_administrativa_id: z.string().nullable(),
})

export type ImprimirCredencialesFormData = z.infer<typeof imprimirCredencialesSchema>
