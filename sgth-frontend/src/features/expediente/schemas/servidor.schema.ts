import { z } from 'zod/v4'

export const servidorSchema = z.object({
  nombres:                  z.string().min(2, 'Mínimo 2 caracteres'),
  apellidos:                z.string().min(2, 'Mínimo 2 caracteres'),
  cedula:                   z.string()
    .length(10, 'Debe tener 10 dígitos')
    .regex(/^\d+$/, 'Solo dígitos'),
  fecha_nacimiento:         z.string().min(1, 'Requerido'),
  genero:                   z.enum(['masculino', 'femenino', 'otro']),
  estado_civil:             z.enum([
    'soltero', 'casado', 'divorciado', 'viudo', 'union_libre'
  ]),
  telefono_personal:        z.string().optional(),
  telefono_institucional:   z.string().optional(),
  correo_personal:          z.string().email('Email inválido').optional()
    .or(z.literal('')),
  correo_institucional:     z.string().email('Email inválido').optional()
    .or(z.literal('')),
  direccion:                z.string().optional(),
  provincia_nacimiento_id:  z.number({ error: 'Seleccione provincia' }),
  canton_nacimiento_id:     z.number({ error: 'Seleccione cantón' }),
})

export type ServidorFormData = z.infer<typeof servidorSchema>
