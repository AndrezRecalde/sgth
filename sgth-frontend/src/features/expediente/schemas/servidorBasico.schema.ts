import { z } from 'zod/v4'

export const servidorBasicoSchema = z.object({
  // Identidad — obligatorios
  nombre:           z.string().min(2, 'Mínimo 2 caracteres'),
  segundo_nombre:   z.string().optional(),
  apellido:         z.string().min(2, 'Mínimo 2 caracteres'),
  segundo_apellido: z.string().optional(),
  cedula:           z.string()
    .length(10, 'Debe tener 10 dígitos')
    .regex(/^\d+$/, 'Solo dígitos'),

  // Datos personales — obligatorios
  fecha_nacimiento: z.string().min(1, 'La fecha de nacimiento es requerida'),
  genero:           z.enum(['masculino', 'femenino', 'otro']),
  estado_civil:     z.enum([
    'soltero', 'casado', 'union_libre', 'divorciado', 'viudo',
  ]),
  tipo_sangre: z.enum([
    'A+','A-','B+','B-','AB+','AB-','O+','O-',
  ]).optional().nullable(),

  // Extranjería
  es_extranjero:           z.boolean(),
  provincia_nacimiento_id: z.number().optional().nullable(),
  canton_nacimiento_id:    z.number().optional().nullable(),
  nacionalidad:            z.string().optional(),
  pais_origen:             z.string().optional(),
  numero_papeleta_votacion: z.string().optional(),
  pasaporte_numero:        z.string().optional(),

  // Condiciones
  tiene_discapacidad:            z.boolean(),
  tiene_enfermedad_catastrofica: z.boolean(),

  // Contacto — opcionales en el registro básico
  telefono_celular:      z.string().optional(),
  telefono_convencional: z.string().optional(),
  correo_personal:       z.string().email('Email inválido')
    .optional().or(z.literal('')),
  direccion_domicilio:   z.string().optional(),
}).superRefine((data, ctx) => {
  if (!data.es_extranjero) {
    if (!data.provincia_nacimiento_id) {
      ctx.addIssue({
        path: ['provincia_nacimiento_id'],
        code: 'custom',
        message: 'Seleccione la provincia de nacimiento',
      })
    }
    if (!data.canton_nacimiento_id) {
      ctx.addIssue({
        path: ['canton_nacimiento_id'],
        code: 'custom',
        message: 'Seleccione el cantón de nacimiento',
      })
    }
  }
})

export type ServidorBasicoFormData = z.infer<typeof servidorBasicoSchema>
