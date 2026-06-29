import { z } from 'zod/v4'

export const itemAdquisicionSchema = z.object({
  inventario_medicina_id: z.number({ error: 'Seleccione la medicina' }),
  nombre_medicina:        z.string(), // solo para mostrar en UI
  cantidad:                z.number().min(1, 'Mínimo 1'),
  lote:                    z.string().optional().nullable(),
  fecha_caducidad:         z.string().optional().nullable(),
  precio_unitario:         z.number().optional().nullable(),
})

export const adquisicionSchema = z.object({
  tipo:                z.enum(['compra', 'donacion']),
  numero_documento:    z.string().min(2, 'Requerido'),
  proveedor_o_donante: z.string().min(2, 'Requerido'),
  fecha_adquisicion:   z.string().min(1, 'Requerido'),
  observaciones:       z.string().optional().nullable(),
  items:               z.array(itemAdquisicionSchema)
    .min(1, 'Agregue al menos un medicamento'),
})

export type AdquisicionFormData = z.infer<typeof adquisicionSchema>
export type ItemAdquisicionFormData = z.infer<typeof itemAdquisicionSchema>
