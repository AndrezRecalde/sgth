import { z } from 'zod/v4'

/** Los topes del backend: PDF, imagen o Word, hasta 10 MB. */
const MAX_BYTES = 10 * 1024 * 1024

export const documentoPostulanteSchema = z.object({
  tipo:    z.string({ error: 'Seleccione el tipo' }).min(1, 'Seleccione el tipo'),
  archivo: z.instanceof(File, { error: 'Seleccione un archivo para subir' })
    .refine((f) => f.size <= MAX_BYTES, 'El archivo no puede pasar de 10 MB'),
})

export type DocumentoPostulanteFormData = z.infer<typeof documentoPostulanteSchema>
