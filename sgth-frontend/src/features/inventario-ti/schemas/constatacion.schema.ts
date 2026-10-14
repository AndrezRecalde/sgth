import { z } from 'zod/v4'

/** Abrir una constatación: el lugar que se recorre y, si se quiere, la unidad. */
export const constatacionSchema = z.object({
  nombre: z.string().trim().min(3, 'Mínimo 3 caracteres').max(150, 'Máximo 150 caracteres'),
  ubicacion_id: z.number({ message: 'Elija la ubicación que va a recorrer' }),
  unidad_administrativa_id: z.number().nullable(),
  observaciones: z.string().trim().max(2000, 'Máximo 2000 caracteres'),
})
export type ConstatacionFormData = z.infer<typeof constatacionSchema>

/** Lo que se lee o se escribe: el código institucional, o la URL de la etiqueta QR. */
export const escaneoSchema = z.object({
  codigo: z.string().trim().min(1, 'Escriba el código del equipo').max(300, 'Máximo 300 caracteres'),
})
export type EscaneoFormData = z.infer<typeof escaneoSchema>

/** Cerrar: lo que no apareció queda como no encontrado. Las observaciones van al acta. */
export const cierreConstatacionSchema = z.object({
  observaciones: z.string().trim().max(2000, 'Máximo 2000 caracteres'),
})
export type CierreConstatacionFormData = z.infer<typeof cierreConstatacionSchema>
