import { z } from 'zod/v4'

/**
 * Las reglas son las de `StoreAdquisicionRequest`. Los topes de longitud y los
 * enteros faltaban: el formulario dejaba escribir un número de documento de
 * 300 caracteres o media caja de un medicamento, y eso volvía del servidor
 * como un 422 suelto, sin señalar el campo que lo causó.
 */

export const itemAdquisicionSchema = z.object({
  inventario_medicina_id: z.number({ error: 'Seleccione la medicina' }),
  nombre_medicina:        z.string(), // solo para mostrar en UI
  cantidad:                z.number()
    .int('Las unidades no se parten por la mitad')
    .min(1, 'Mínimo 1'),
  lote:                    z.string().max(100, 'Máximo 100 caracteres')
    .optional().nullable(),
  fecha_caducidad:         z.string().optional().nullable(),
  // El único decimal legítimo del formulario: un precio sí lleva centavos.
  precio_unitario:         z.number().min(0, 'No puede ser negativo')
    .optional().nullable(),
})

export const adquisicionSchema = z.object({
  tipo:                z.enum(['compra', 'donacion']),
  numero_documento:    z.string().min(2, 'Requerido')
    .max(100, 'Máximo 100 caracteres'),
  proveedor_o_donante: z.string().min(2, 'Requerido')
    .max(255, 'Máximo 255 caracteres'),
  fecha_adquisicion:   z.string().min(1, 'Requerido'),
  observaciones:       z.string().max(1000, 'Máximo 1000 caracteres')
    .optional().nullable(),
  items:               z.array(itemAdquisicionSchema)
    .min(1, 'Agregue al menos un medicamento'),
})

export type AdquisicionFormData = z.infer<typeof adquisicionSchema>
export type ItemAdquisicionFormData = z.infer<typeof itemAdquisicionSchema>
