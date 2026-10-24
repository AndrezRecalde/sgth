import { z } from 'zod/v4'

/** Los motivos de `MotivoNoEntregado`, en el orden en que se ofrecen. */
export const MOTIVOS_NO_ENTREGADO = [
  { value: 'agotado', label: 'Agotado' },
  { value: 'vencido', label: 'Vencido' },
  { value: 'otro',    label: 'Otro motivo' },
] as const

export type MotivoNoEntregado = (typeof MOTIVOS_NO_ENTREGADO)[number]['value']

/**
 * Un medicamento que el mostrador no va a entregar (decisión del Dispensario
 * del 2026-10-24, opción A): el motivo siempre; el detalle, si es «otro». Los
 * nombres y topes son los del servidor, para que cada 422 caiga en su campo.
 */
export const noEntregadoSchema = z.object({
  motivo:  z.enum(['agotado', 'vencido', 'otro'], { error: 'Elija el motivo' }),
  detalle: z.string().trim().max(255, 'Hasta 255 caracteres'),
}).refine((v) => v.motivo !== 'otro' || v.detalle.length > 0, {
  path:    ['detalle'],
  message: 'Explique por qué no se entrega: queda en la receta',
})

export type NoEntregadoFormData = z.infer<typeof noEntregadoSchema>
