import { z } from 'zod/v4'

const base = {
  nombre:   z.string().min(3, 'Mínimo 3 caracteres'),
  // El backend lo exige y es único: pedirlo opcional solo conseguía un 422
  // después de llenar el formulario entero.
  codigo:   z.string().min(1, 'El código es obligatorio').max(50, 'Máximo 50 caracteres'),
  acronimo: z.string().max(50, 'Máximo 50 caracteres').optional().nullable(),
  // UUID, no número: `tipos_unidad.id` es uuid en la base.
  //
  // Obligatorio aunque la columna admita nulos: el organigrama agrupa por
  // tipo de proceso, y una unidad sin él se archivaba en «agregadores de
  // valor» sin que nadie lo hubiera elegido, tanto en el gráfico como en el
  // PDF. Las 59 unidades registradas ya tienen tipo, así que exigirlo no
  // bloquea la edición de nada existente.
  tipo_unidad_id:  z.uuid('Seleccione el tipo de proceso'),
  unidad_padre_id: z.number().optional().nullable(),
  descripcion:     z.string().optional().nullable(),
  estado:          z.boolean().optional(),
  // Anclajes de los firmantes de las Acciones de Personal: el jefe de estas
  // unidades es quien firma. Solo una unidad puede llevar cada bandera; el
  // backend desmarca la anterior al mover el anclaje.
  es_unidad_talento_humano: z.boolean().optional(),
  es_maxima_autoridad:      z.boolean().optional(),
}

export const unidadSchema = z.object(base)

/**
 * El mismo esquema, con la unidad superior obligatoria.
 *
 * El orgánico tiene una sola raíz. En cuanto la institución está registrada,
 * dejar «Depende de» en blanco crea una segunda raíz que el organigrama de
 * nodos y el PDF no dibujan —ambos parten de la primera—, así que la unidad
 * se guardaba bien y era invisible. El backend ya lo rechaza; esto evita
 * llenar el formulario entero para enterarse.
 *
 * Se elige por caso y no de forma fija porque la propia raíz sí se edita sin
 * padre, y la primera unidad de todas tiene que poder crearse sin uno.
 */
export const unidadConPadreSchema = z.object({
  ...base,
  unidad_padre_id: z.number({
    error: 'Indique de qué unidad depende',
  }),
})

export type UnidadFormData = z.infer<typeof unidadSchema>
