import { z } from 'zod/v4'

/**
 * El archivo no está aquí: llega como `File` desde la zona de carga y el
 * formulario lo valida aparte, porque no viaja por React Hook Form.
 */
export const documentoSchema = z.object({
  tipo_documento:    z.string().min(1, 'Seleccione el tipo'),
  descripcion:       z.string().optional(),
  // `null` al borrar la fecha con la X del selector: sin `nullable` el
  // formulario quedaba bloqueado con un error de tipo en inglés.
  fecha_vencimiento: z.string().optional().nullable(),
})

export type DocumentoFormData = z.infer<typeof documentoSchema>
