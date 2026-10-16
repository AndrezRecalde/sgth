import { z } from 'zod/v4'
import { renglonSchema } from './movimiento.schema'

/**
 * La solicitud a bodega (5.4c). Desde un ticket, el servidor y el equipo salen
 * de él; sin ticket, el servidor es obligatorio. Hasta 25 renglones, como el
 * formulario de hoy.
 */
export const solicitudSchema = z.object({
  desde_ticket: z.boolean(),
  servidor_id: z.number().nullable(),
  bien_informatico_id: z.number().nullable(),
  motivo: z.string().trim().min(5, 'Diga para qué es: al menos 5 caracteres').max(500, 'Máximo 500 caracteres'),
  items: z.array(renglonSchema.extend({ cantidad: z.number({ message: 'Escriba la cantidad' }).int('Sin decimales').min(1, 'Al menos 1').max(999, 'Máximo 999') }))
    .min(1, 'Agregue al menos un consumible')
    .max(25, 'Hasta 25 renglones, como el formulario'),
}).refine((v) => v.desde_ticket || v.servidor_id !== null, {
  message: 'Diga de parte de quién es la solicitud', path: ['servidor_id'],
}).refine((v) => new Set(v.items.map((i) => i.consumible_id)).size === v.items.length, {
  message: 'Cada consumible va una sola vez: sume las cantidades en un renglón', path: ['items'],
})
export type SolicitudFormData = z.infer<typeof solicitudSchema>

/** Lo que despachó Bodega, renglón por renglón (0 = no despachó). */
export const despachoSchema = z.object({
  fecha: z.string().min(1, 'Elija la fecha'),
  items: z.array(z.object({
    item_id: z.number(),
    cantidad_despachada: z.number({ message: 'Escriba la cantidad' }).int('Sin decimales').min(0, 'No puede ser negativa'),
  })),
}).refine((v) => v.items.some((i) => i.cantidad_despachada > 0), {
  message: 'Bodega no despachó nada: anote al menos una cantidad, o anule la solicitud', path: ['items'],
})
export type DespachoFormData = z.infer<typeof despachoSchema>
