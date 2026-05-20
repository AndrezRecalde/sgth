import { z } from 'zod/v4'

export const loginSchema = z.object({
  usuario: z.string().min(3, 'Ingrese su usuario'),
  contrasena: z.string().min(6, 'La contraseña debe tener mínimo 6 caracteres'),
})

export type LoginFormData = z.infer<typeof loginSchema>
