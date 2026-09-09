import { z } from 'zod/v4'

/**
 * Un ítem de receta es una de dos cosas: un medicamento del catálogo de la
 * farmacia, y entonces lleva `inventario_medicina_id`, o uno que el
 * dispensario no maneja, y entonces lleva su nombre en `medicamento_externo`.
 * Nunca las dos, nunca ninguna: el servidor lo comprueba igual, y la tabla
 * tiene un CHECK que lo garantiza.
 *
 * `nombre` es solo para pintar la ficha mientras se llena el formulario; no
 * viaja al servidor.
 */
export const itemRecetaSchema = z.object({
  inventario_medicina_id: z.number().nullable(),
  medicamento_externo:    z.string().nullable(),
  nombre:                 z.string(),
  cantidad_prescrita:     z.number().min(1, 'Mínimo 1'),
  dosis:                  z.string().min(1, 'Requerido'),
  frecuencia:             z.string().min(1, 'Requerido'),
  duracion:               z.string().min(1, 'Requerido'),
  observaciones:          z.string().optional().nullable(),
}).refine(
  (item) =>
    (item.inventario_medicina_id !== null) !==
    (!!item.medicamento_externo?.trim()),
  {
    error: 'Elija la medicina del catálogo o escriba su nombre como externa',
    path:  ['inventario_medicina_id'],
  },
)

export const recetaSchema = z.object({
  indicaciones_generales: z.string().optional().nullable(),
  items: z.array(itemRecetaSchema).min(1, 'Agregue al menos un medicamento'),
})

export type RecetaFormData = z.infer<typeof recetaSchema>
export type ItemRecetaFormData = z.infer<typeof itemRecetaSchema>
