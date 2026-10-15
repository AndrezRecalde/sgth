import { z } from 'zod/v4'

/** Los datos de la institución (docs/diseno-comunicacion-social.md, 4.5). */
export const datosInstitucionalesSchema = z.object({
  nombre_oficial: z.string().trim().min(1, 'Escriba el nombre oficial').max(200, 'Hasta 200 caracteres'),
  siglas: z.string().trim().min(1, 'Escriba las siglas').max(20, 'Hasta 20 caracteres'),
  nombre_corto: z.string().trim().min(1, 'Escriba el nombre corto').max(60, 'Hasta 60 caracteres'),
  ruc: z.string().trim().regex(/^\d{13}$/, 'El RUC tiene 13 dígitos'),
  direccion: z.string().trim().min(1, 'Escriba la dirección').max(255, 'Hasta 255 caracteres'),
  telefono: z.string().trim().min(1, 'Escriba el teléfono').max(40, 'Hasta 40 caracteres'),
  sitio_web: z.string().trim().max(120, 'Hasta 120 caracteres').nullable(),
})

export type DatosInstitucionalesFormData = z.infer<typeof datosInstitucionalesSchema>
