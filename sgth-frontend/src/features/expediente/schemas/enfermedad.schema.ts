import { z } from 'zod/v4'

export const enfermedadSchema = z.object({
  tipo_enfermedad:    z.string().min(2, 'Mínimo 2 caracteres'),
  codigo_cie10:       z.string().optional().nullable(),
  fecha_diagnostico:  z.string().optional().nullable(),
})

export type EnfermedadFormData = z.infer<typeof enfermedadSchema>
