import { z } from 'zod/v4'

export const cambiarPasswordSchema = z.object({
  password_actual: z.string().min(6, 'Mínimo 6 caracteres'),
  password_nuevo: z.string().min(8, 'Mínimo 8 caracteres'),
  password_confirmacion: z.string().min(8, 'Mínimo 8 caracteres'),
}).refine((data) => data.password_nuevo === data.password_confirmacion, {
  message: 'Las contraseñas no coinciden',
  path: ['password_confirmacion'],
})

export type CambiarPasswordFormData = z.infer<typeof cambiarPasswordSchema>
