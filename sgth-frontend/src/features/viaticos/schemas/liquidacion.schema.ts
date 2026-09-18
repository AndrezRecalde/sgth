import { z } from 'zod/v4'
import type { EstadoRevisionComprobante } from '@/types/api'

/*
| Lo que presenta el servidor al liquidar: las actividades de cada día y los
| comprobantes de los gastos. Antes los dos esquemas vivían dentro de sus
| modales y los tipos se declaraban aparte, a mano.
*/

export const TIPOS_COMPROBANTE = ['factura', 'ticket', 'recibo', 'otro'] as const

export const comprobanteSchema = z
  .object({
    categoria_factura_id: z.number().min(1, 'Seleccione la categoría'),
    // Nulo mientras el campo está vacío: el calendario lo deja así al borrarlo.
    fecha_factura: z
      .string()
      .nullable()
      .refine((v) => !!v, 'Indique la fecha del comprobante'),
    tipo_comprobante: z.enum(TIPOS_COMPROBANTE),
    numero_factura: z.string().optional().nullable(),
    numero_ticket: z.string().optional().nullable(),
    ruc_proveedor: z.string().optional().nullable(),
    nombre_proveedor: z.string().trim().min(1, 'Indique el proveedor'),
    detalle: z.string().optional().nullable(),
    monto: z.number().min(0.01, 'Mínimo $0.01'),
  })
  .refine(
    (c) => !['factura', 'recibo'].includes(c.tipo_comprobante) || !!c.ruc_proveedor?.trim(),
    { message: 'El RUC es obligatorio en una factura o un recibo', path: ['ruc_proveedor'] },
  )

export const comprobantesSchema = z.object({
  facturas: z.array(comprobanteSchema).min(1, 'Agregue al menos un comprobante'),
})

export type ComprobantesFormData = z.infer<typeof comprobantesSchema>
export type ComprobanteForm = ComprobantesFormData['facturas'][number]

export const actividadSchema = z.object({
  fecha:       z.string().min(1, 'Seleccione la fecha'),
  hora_inicio: z.string().min(1, 'Indique la hora de inicio'),
  hora_fin:    z.string().min(1, 'Indique la hora de fin'),
  descripcion: z.string().trim().min(5, 'Mínimo 5 caracteres'),
  lugar:       z.string().trim().min(1, 'Indique el lugar'),
})

export const actividadesSchema = z.object({
  actividades: z.array(actividadSchema).min(1, 'Agregue al menos una actividad'),
})

export type ActividadesFormData = z.infer<typeof actividadesSchema>
export type ActividadData = z.infer<typeof actividadSchema>

/**
 * Un comprobante como se muestra: lo que se captura más la revisión de
 * Financiero, que no se envía al guardar.
 */
export type FacturaData = ComprobanteForm & {
  estado_revision?: EstadoRevisionComprobante
  observacion_revision?: string | null
}
