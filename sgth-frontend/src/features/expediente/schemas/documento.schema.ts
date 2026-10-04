import { z } from 'zod/v4'

/**
 * El archivo va dentro del esquema, como cualquier campo (regla 07): antes
 * vivía en un `useState` del modal y su error se pintaba aparte.
 */
export const documentoSchema = z.object({
  tipo_documento:    z.string().min(1, 'Seleccione el tipo'),
  archivo:           z.instanceof(File, { error: 'Seleccione un archivo para subir' }),
  descripcion:       z.string().optional(),
  // `null` al borrar la fecha con la X del selector: sin `nullable` el
  // formulario quedaba bloqueado con un error de tipo en inglés.
  fecha_vencimiento: z.string().optional().nullable(),
})

export type DocumentoFormData = z.infer<typeof documentoSchema>
