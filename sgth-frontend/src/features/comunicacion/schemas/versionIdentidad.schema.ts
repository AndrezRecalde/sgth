import { z } from 'zod/v4'

/** Una versión nueva de un logo: el archivo, desde cuándo rige y una nota. */
export const versionIdentidadSchema = z.object({
  archivo: z.instanceof(File, { message: 'Elija el archivo del logo' }),
  // YYYY-MM-DD; vacío es «desde hoy».
  vigente_desde: z.string().nullable(),
  nota: z.string().max(255, 'Hasta 255 caracteres').optional(),
})

export type VersionIdentidadFormData = z.infer<typeof versionIdentidadSchema>
