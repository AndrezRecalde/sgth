import { z } from 'zod/v4'

export const declaracionSchema = z.object({
  tipo_declaracion:  z.enum([
    'inicio_gestion',
    'periodica',
    'fin_gestion',
  ]),
  fecha_declaracion: z.string().min(1, 'La fecha es requerida'),
  codigo_barras:     z.string().min(1, 'El código de barras es requerido'),
  observaciones:     z.string().optional(),
})

export type DeclaracionFormData = z.infer<typeof declaracionSchema>
