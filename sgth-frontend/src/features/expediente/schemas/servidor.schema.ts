import { z } from 'zod/v4'

export const servidorSchema = z.object({
  // Identidad
  nombre:           z.string().min(2, 'Mínimo 2 caracteres'),
  segundo_nombre:   z.string().optional(),
  apellido:         z.string().min(2, 'Mínimo 2 caracteres'),
  segundo_apellido: z.string().optional(),
  cedula:           z.string()
    .length(10, 'Debe tener 10 dígitos')
    .regex(/^\d+$/, 'Solo dígitos'),

  // Régimen y cargo
  regimen_laboral:          z.enum(['losep', 'codigo_trabajo']),
  unidad_administrativa_id: z.number({ error: 'Seleccione unidad' }),
  puesto_id:                z.number({ error: 'Seleccione puesto' }),

  // Datos personales
  fecha_nacimiento: z.string().min(1, 'Requerido'),
  genero:           z.enum(['masculino', 'femenino', 'otro']),
  estado_civil:     z.enum([
    'soltero', 'casado', 'union_libre', 'divorciado', 'viudo',
  ]),
  tipo_sangre: z.enum([
    'A+','A-','B+','B-','AB+','AB-','O+','O-',
  ]).optional().nullable(),

  // Extranjería
  es_extranjero:            z.boolean().default(false),
  provincia_nacimiento_id:  z.number().optional().nullable(),
  canton_nacimiento_id:     z.number().optional().nullable(),
  nacionalidad:             z.string().optional(),
  pais_origen:              z.string().optional(),

  // Documentos
  numero_papeleta_votacion: z.string().optional(),
  pasaporte_numero:         z.string().optional(),

  // Contacto
  telefono_celular:       z.string().optional(),
  telefono_convencional:  z.string().optional(),

  correo_personal:        z.string().email('Email inválido')
    .optional().or(z.literal('')),
  direccion_domicilio:    z.string().optional(),

  // Condiciones
  tiene_discapacidad:           z.boolean().default(false),
  tiene_enfermedad_catastrofica: z.boolean().default(false),

  // Datos laborales
  tipo_nombramiento: z.enum([
    'nombramiento_permanente',
    'nombramiento_provisional',
    'servicios_ocasionales',
    'libre_nombramiento_remocion',
    'codigo_trabajo',
    'servicios_profesionales',
    'eleccion_popular',
  ]),
  numero_contrato:              z.string().optional(),
  fecha_ingreso_institucion:    z.string().min(1, 'Requerido'),
  fecha_ingreso_sector_publico: z.string().optional().nullable(),
  fecha_nombramiento:           z.string().optional().nullable(),
  fecha_inicio_ultimo_contrato: z.string().optional().nullable(),
  fecha_fin_ultimo_contrato:    z.string().optional().nullable(),
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

export type ServidorFormData = z.infer<typeof servidorSchema>
