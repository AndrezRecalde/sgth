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
 *
 * Los topes son los de `StoreRecetaMedicaRequest`. Faltaban —solo los tenía el
 * esquema de editar— y una posología larga volvía como un 422 en un aviso
 * suelto, sin señalar el campo.
 */
export const itemRecetaSchema = z.object({
  inventario_medicina_id: z.number().nullable(),
  medicamento_externo:    z.string().max(255, 'El nombre admite hasta 255 caracteres').nullable(),
  nombre:                 z.string(),
  // Vacía mientras se reescribe: el campo se puede borrar, y entonces lo dice.
  cantidad_prescrita:     z.number({ error: 'Indique la cantidad' })
    .int('Las unidades no se parten por la mitad')
    .min(1, 'Debe ser al menos 1'),
  dosis:                  z.string().min(1, 'Indique la dosis').max(100, 'Máximo 100 caracteres'),
  frecuencia:             z.string().min(1, 'Indique la frecuencia').max(100, 'Máximo 100 caracteres'),
  duracion:               z.string().min(1, 'Indique la duración').max(100, 'Máximo 100 caracteres'),
  observaciones:          z.string().max(500, 'Máximo 500 caracteres').optional().nullable(),
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
  indicaciones_generales: z.string().max(1000, 'Máximo 1000 caracteres').optional().nullable(),
  /**
   * Solo afecta al impreso; las alergias siguen en la historia clínica y a la
   * vista del dispensario. Viene en `true`: la receta pasa por manos de
   * compañeros de trabajo del paciente y una alergia sigue siendo dato de
   * salud. El médico lo desmarca cuando conviene que viajen en el papel —el
   * paciente va a comprar fuera y quien despache allí no puede saberlas de
   * otro modo—.
   */
  omitir_alergias:        z.boolean(),
  items: z.array(itemRecetaSchema).min(1, 'Agregue al menos un medicamento'),
})

export type RecetaFormData = z.infer<typeof recetaSchema>
