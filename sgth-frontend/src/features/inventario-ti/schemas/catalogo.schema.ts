import { z } from 'zod/v4'

/**
 * Los catálogos simples del inventario. Que un nombre no se repita (sin
 * distinguir mayúsculas) lo comprueba el backend y su respuesta cae en el
 * campo.
 */
export const tipoSchema = z.object({
  nombre: z.string().trim().min(2, 'Mínimo 2 caracteres').max(100, 'Máximo 100 caracteres'),
  anios_vida_util: z.number({ message: 'Indique los años (0 si aún no se sabe)' }).int().min(0, 'No puede ser negativo').max(30, 'Máximo 30 años'),
})
export type TipoFormData = z.infer<typeof tipoSchema>

export const nombreSchema = z.object({
  nombre: z.string().trim().min(1, 'Indique el nombre').max(100, 'Máximo 100 caracteres'),
})
export type NombreFormData = z.infer<typeof nombreSchema>

export const ubicacionSchema = z.object({
  sede: z.string().trim().min(2, 'Indique la sede').max(100, 'Máximo 100 caracteres'),
  piso: z.string().trim().max(30, 'Máximo 30 caracteres'),
  descripcion: z.string().trim().max(200, 'Máximo 200 caracteres'),
})
export type UbicacionFormData = z.infer<typeof ubicacionSchema>
