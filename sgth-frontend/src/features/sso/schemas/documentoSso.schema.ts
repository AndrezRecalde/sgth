import { z } from 'zod/v4'

/**
 * El adjunto de respaldo de un registro SSO (Fase 9).
 *
 * Los tres límites salen del backend (`DocumentoSsoController::store`), y
 * estaban escritos solo ahí: el formulario los mandaba y recibía el 422 de
 * vuelta, así que subir un PDF de 12 MB costaba el viaje entero. Aquí se
 * comprueban antes de salir, con el motivo dicho.
 */

/** `mimes:pdf,doc,docx,jpg,jpeg,png` del backend, en tipos MIME. */
export const MIMES_DOCUMENTO_SSO = [
  'application/pdf',
  'image/jpeg',
  'image/png',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]

/** `max:10240` del backend, que son kilobytes. */
export const MAX_BYTES_DOCUMENTO_SSO = 10 * 1024 * 1024

export const documentoSsoSchema = z.object({
  nombre: z
    .string()
    .trim()
    .min(1, 'Indique un nombre para el documento')
    .max(255, 'Máximo 255 caracteres'),
  /**
   * `z.custom` y no `z.instanceof(File)`: el segundo evalúa `File` al
   * construir el esquema, y este módulo lo importa un componente de cliente
   * que Next también resuelve en el servidor. `custom` difiere la
   * comprobación al momento de validar, que siempre ocurre en el navegador.
   */
  archivo: z
    .custom<File>((valor) => valor instanceof File, 'Seleccione un archivo para subir')
    .refine(
      (archivo) => archivo.size <= MAX_BYTES_DOCUMENTO_SSO,
      'El archivo supera los 10 MB',
    )
    .refine(
      (archivo) => MIMES_DOCUMENTO_SSO.includes(archivo.type),
      'Solo se admiten PDF, DOC, DOCX, JPG o PNG',
    ),
})

export type DocumentoSsoFormData = z.infer<typeof documentoSsoSchema>
