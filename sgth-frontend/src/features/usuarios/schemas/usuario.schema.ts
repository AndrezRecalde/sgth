import { z } from 'zod/v4'

export const usuarioSchema = z.object({
  nombre:      z.string().min(2, 'Mínimo 2 caracteres'),
  apellido:    z.string().min(2, 'Mínimo 2 caracteres'),
  email:       z.string().email('Email inválido'),
  cedula:      z.string()
    .length(10, 'Debe tener 10 dígitos')
    .regex(/^\d+$/, 'Solo dígitos'),
  roles:       z.array(z.string()).min(1, 'Asigne al menos un rol'),
  servidor_id: z.number().optional().nullable(),
})

export type UsuarioFormData = z.infer<typeof usuarioSchema>
