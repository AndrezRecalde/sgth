import { z } from 'zod/v4'

export const enfermedadSchema = z.object({
  tipo_enfermedad:    z.string().min(2, 'Mínimo 2 caracteres'),
  // La columna admite 10 caracteres: «C18.0», «N18.5».
  codigo_cie10:       z.string().max(10, 'Máximo 10 caracteres').optional().nullable(),
  fecha_diagnostico:  z.string().optional().nullable(),
})

export type EnfermedadFormData = z.infer<typeof enfermedadSchema>
