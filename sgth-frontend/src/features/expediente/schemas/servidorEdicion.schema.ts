import { z } from 'zod/v4'

/**
 * Edición de una ficha ya creada. A diferencia del alta, aquí **nada es
 * obligatorio**: lo que se envía se valida en su formato, y lo que no se tocó
 * se queda como está.
 *
 * El motivo es que las fichas cargadas antes del sistema vienen incompletas
 * (sin fecha de nacimiento, sin cantón), y con el esquema del alta corregir un
 * teléfono obligaba a completar media ficha antes de poder guardar.
 */
export const servidorEdicionSchema = z.object({
  nombre:           z.string().min(2, 'Mínimo 2 caracteres'),
  segundo_nombre:   z.string().optional(),
  apellido:         z.string().min(2, 'Mínimo 2 caracteres'),
  segundo_apellido: z.string().optional(),
  cedula:           z.string()
    .length(10, 'Debe tener 10 dígitos')
    .regex(/^\d+$/, 'Solo dígitos'),

  fecha_nacimiento: z.string().optional(),
  genero:           z.enum(['masculino', 'femenino', 'otro']),
  estado_civil:     z.enum([
    'soltero', 'casado', 'union_libre', 'divorciado', 'viudo',
  ]),
  tipo_sangre: z.enum([
    'A+','A-','B+','B-','AB+','AB-','O+','O-',
  ]).optional().nullable(),

  es_extranjero:           z.boolean(),
  provincia_nacimiento_id: z.number().optional().nullable(),
  canton_nacimiento_id:    z.number().optional().nullable(),
  nacionalidad:            z.string().optional(),
  pais_origen:             z.string().optional(),
  numero_papeleta_votacion: z.string().optional(),
  pasaporte_numero:        z.string().optional(),

  telefono_celular:      z.string().optional(),
  telefono_convencional: z.string().optional(),
  correo_personal:       z.string().email('Email inválido')
    .optional().or(z.literal('')),
  direccion_domicilio:   z.string().optional(),
  codigo_medico:         z.string().max(30, 'Máximo 30 caracteres').optional(),

  fecha_ingreso_sector_publico: z.string().optional().nullable(),
  fecha_nombramiento:           z.string().optional().nullable(),
})

export type ServidorEdicionFormData = z.infer<typeof servidorEdicionSchema>

/** Los datos que la ficha necesita para estar completa, con su nombre. */
export const CAMPOS_OBLIGATORIOS_DE_LA_FICHA = {
  fecha_nacimiento: 'fecha de nacimiento',
  provincia_nacimiento_id: 'provincia de nacimiento',
  canton_nacimiento_id: 'cantón de nacimiento',
} as const

/**
 * Qué le falta a la ficha. Un servidor extranjero no tiene provincia ni
 * cantón, así que ahí solo se mira la fecha de nacimiento.
 */
export function camposQueFaltan(
  valores: Partial<ServidorEdicionFormData>,
): string[] {
  const faltan: string[] = []
  if (!valores.fecha_nacimiento) faltan.push(CAMPOS_OBLIGATORIOS_DE_LA_FICHA.fecha_nacimiento)
  if (!valores.es_extranjero) {
    if (!valores.provincia_nacimiento_id) {
      faltan.push(CAMPOS_OBLIGATORIOS_DE_LA_FICHA.provincia_nacimiento_id)
    }
    if (!valores.canton_nacimiento_id) {
      faltan.push(CAMPOS_OBLIGATORIOS_DE_LA_FICHA.canton_nacimiento_id)
    }
  }
  return faltan
}
