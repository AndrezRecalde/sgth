import { z } from 'zod/v4'

export const declaracionSchema = z.object({
  tipo_declaracion:   z.enum(['ingreso','salida','actualizacion']),
  fecha_declaracion:  z.string().min(1, 'Requerido'),
  codigo_barras:      z.string().optional(),
  observaciones:      z.string().optional(),
})

export type DeclaracionFormData = z.infer<typeof declaracionSchema>
