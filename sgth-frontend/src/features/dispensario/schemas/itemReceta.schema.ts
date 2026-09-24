import { z } from 'zod/v4'

/**
 * Corregir un medicamento ya recetado. Las reglas son las del `update` de
 * `ItemRecetaController`.
 *
 * Los cuatro topes de longitud y el entero faltaban: se podía escribir una
 * posología de trescientos caracteres, o recetar media cápsula, y el rechazo
 * llegaba del servidor sin señalar qué campo lo causaba.
 */
export const itemRecetaSchema = z.object({
  cantidad_prescrita: z.number()
    .int('Las unidades no se parten por la mitad')
    .min(1, 'Debe ser al menos 1'),
  dosis:        z.string().min(1, 'Indique la dosis')
    .max(100, 'Máximo 100 caracteres'),
  frecuencia:   z.string().min(1, 'Indique la frecuencia')
    .max(100, 'Máximo 100 caracteres'),
  duracion:     z.string().min(1, 'Indique la duración')
    .max(100, 'Máximo 100 caracteres'),
  observaciones: z.string().max(500, 'Máximo 500 caracteres')
    .optional().nullable(),
})

export type ItemRecetaFormData = z.infer<typeof itemRecetaSchema>
