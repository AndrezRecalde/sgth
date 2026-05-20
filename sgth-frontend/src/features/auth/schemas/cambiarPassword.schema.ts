import { z } from 'zod/v4'

export const cambiarPasswordSchema = z.object({
  nueva_contrasena: z
    .string()
    .min(8, 'Mínimo 8 caracteres')
    .regex(/[a-zA-Z]/, 'Debe contener al menos una letra')
    .regex(/[0-9]/, 'Debe contener al menos un número'),
  confirmar_contrasena: z
    .string()
    .min(8, 'Mínimo 8 caracteres'),
}).refine(
  (data) => data.nueva_contrasena === data.confirmar_contrasena,
  {
    message: 'Las contraseñas no coinciden',
    path: ['confirmar_contrasena'],
  }
)

export type CambiarPasswordFormData = z.infer<typeof cambiarPasswordSchema>

// Tipo que el backend realmente espera
export type CambiarPasswordPayload = {
  nueva_contrasena: string
}
