import { z } from 'zod/v4'

export const usuarioSchema = z.object({
  email:       z.string().email('Email inválido'),
  roles:       z.array(z.string()).min(1, 'Asigne al menos un rol'),
  servidor_id: z.number().optional().nullable(),
  cedula:      z.string()
    .regex(/^\d{10}$/, 'Debe tener 10 dígitos')
    .optional()
    .nullable(),
})

export type UsuarioFormData = z.infer<typeof usuarioSchema>
