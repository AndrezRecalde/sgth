import { z } from 'zod/v4'

// Solo que no vengan vacíos. Exigir una longitud mínima a la contraseña en el
// login no protege nada —eso se decide al crearla— y a quien la tiene más
// corta le daba un error que no le dejaba ni intentarlo.
export const loginSchema = z.object({
  usuario:    z.string().trim().min(1, 'Ingrese su usuario'),
  contrasena: z.string().min(1, 'Ingrese su contraseña'),
})

export type LoginFormData = z.infer<typeof loginSchema>
