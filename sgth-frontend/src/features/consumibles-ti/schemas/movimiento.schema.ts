import { z } from 'zod/v4'

const fecha = z.string().min(1, 'Elija la fecha')
const cantidad = (max: number) => z.number({ message: 'Escriba la cantidad' }).int('Sin decimales').min(1, 'Al menos 1').max(max, `Máximo ${max}`)

/** Un renglón de un ingreso o una entrega: un consumible y cuántos. */
export const renglonSchema = z.object({
  consumible_id: z.number({ message: 'Elija el consumible' }),
  cantidad: cantidad(9999),
})

/** Lo que TIC recibe de bodega: un documento con uno o varios consumibles [P8]. */
export const ingresoSchema = z.object({
  fecha,
  tipo_documento: z.enum(['egreso_bodega', 'acta', 'memorando'], { message: 'Elija el documento' }),
  numero_documento: z.string().trim().min(1, 'Escriba el número del documento').max(80, 'Máximo 80 caracteres'),
  fecha_compra: z.string(),
  observacion: z.string().trim().max(500, 'Máximo 500 caracteres'),
  items: z.array(renglonSchema).min(1, 'Agregue al menos un consumible'),
}).refine((v) => !v.fecha_compra || !v.fecha || v.fecha_compra <= v.fecha, {
  message: 'La compra no puede ir después del ingreso', path: ['fecha_compra'],
}).refine((v) => new Set(v.items.map((i) => i.consumible_id)).size === v.items.length, {
  message: 'Cada consumible va una sola vez: sume las cantidades en un renglón', path: ['items'],
})
export type IngresoFormData = z.infer<typeof ingresoSchema>

/** El saldo contado el día de arranque [P7]. */
export const aperturaSchema = z.object({
  fecha,
  cantidad: cantidad(99999),
  observacion: z.string().trim().max(500, 'Máximo 500 caracteres'),
})
export type AperturaFormData = z.infer<typeof aperturaSchema>

/** Suma o resta, con su motivo y el detalle. */
export const ajusteSchema = z.object({
  fecha,
  sentido: z.enum(['sumar', 'restar']),
  cantidad: cantidad(99999),
  motivo_ajuste: z.enum(['dano', 'caducidad', 'diferencia_conteo', 'otro'], { message: 'Elija el motivo' }),
  observacion: z.string().trim().min(5, 'Cuente qué pasó: al menos 5 caracteres').max(500, 'Máximo 500 caracteres'),
})
export type AjusteFormData = z.infer<typeof ajusteSchema>

/**
 * La entrega: a quién retira y la fecha; los renglones se arman en la pantalla
 * con los consumibles compatibles del equipo.
 */
export const entregaSchema = z.object({
  fecha,
  servidor_retira_id: z.number().nullable(),
  observacion: z.string().trim().max(500, 'Máximo 500 caracteres'),
})
export type EntregaFormData = z.infer<typeof entregaSchema>
