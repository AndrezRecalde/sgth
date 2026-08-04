import { z } from 'zod/v4'

/**
 * Carga inicial: ficha personal + contrato vigente en un solo acto.
 *
 * No reutiliza servidorBasicoSchema por composición porque aquel lleva un
 * superRefine y encadenarlos oscurece de dónde sale cada error. Aquí los
 * campos se repiten a propósito: esta es una vía excepcional y temporal, y si
 * mañana cambia una regla del alta ordinaria no debe cambiar en silencio la de
 * la migración.
 */
const NOMBRAMIENTOS = [
  'nombramiento_permanente',
  'nombramiento_provisional',
  'servicios_ocasionales',
  'libre_nombramiento_remocion',
  'codigo_trabajo',
  'servicios_profesionales',
  'eleccion_popular',
] as const

/** Vínculos cuyo plazo debe constar: sin fecha de fin no se sabe cuándo vencen. */
const CON_PLAZO_OBLIGATORIO = ['servicios_ocasionales']

export const vinculacionInicialSchema = z.object({
  // ── Ficha personal ──────────────────────────────────────
  nombre:           z.string().min(2, 'Mínimo 2 caracteres'),
  segundo_nombre:   z.string().optional(),
  apellido:         z.string().min(2, 'Mínimo 2 caracteres'),
  segundo_apellido: z.string().optional(),
  cedula: z.string()
    .length(10, 'Debe tener 10 dígitos')
    .regex(/^\d+$/, 'Solo dígitos'),

  fecha_nacimiento: z.string().min(1, 'La fecha de nacimiento es requerida'),
  genero:           z.enum(['masculino', 'femenino', 'otro']),
  estado_civil:     z.enum(['soltero', 'casado', 'union_libre', 'divorciado', 'viudo']),
  tipo_sangre: z.enum(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])
    .optional().nullable(),

  es_extranjero:           z.boolean(),
  provincia_nacimiento_id: z.number().optional().nullable(),
  canton_nacimiento_id:    z.number().optional().nullable(),
  nacionalidad:            z.string().optional(),
  pais_origen:             z.string().optional(),
  numero_papeleta_votacion: z.string().optional(),
  pasaporte_numero:        z.string().optional(),

  tiene_discapacidad:            z.boolean(),
  tiene_enfermedad_catastrofica: z.boolean(),

  telefono_celular:      z.string().optional(),
  telefono_convencional: z.string().optional(),
  correo_personal:       z.string().email('Correo inválido').optional().or(z.literal('')),
  direccion_domicilio:   z.string().optional(),

  // Antigüedad real en la institución. Puede ser muy anterior al contrato
  // vigente si la persona tuvo vínculos previos.
  fecha_ingreso_institucion:    z.string().optional().nullable(),
  fecha_ingreso_sector_publico: z.string().optional().nullable(),

  // ── Contrato vigente ────────────────────────────────────
  vinculo: z.object({
    tipo_nombramiento:        z.enum(NOMBRAMIENTOS),
    unidad_administrativa_id: z.number({ error: 'Seleccione la unidad administrativa' }),
    puesto_id:                z.number({ error: 'Seleccione el puesto' }),
    fecha_inicio:             z.string().min(1, 'La fecha de inicio es requerida'),
    fecha_fin:                z.string().optional().nullable(),
    remuneracion:             z.number({ error: 'Ingrese la remuneración vigente' }).min(0),
    numero_contrato:          z.string().optional().nullable(),
    resolucion_numero:        z.string().optional().nullable(),
    puede_marcar:             z.boolean().optional().nullable(),
  }),
}).superRefine((data, ctx) => {
  if (!data.es_extranjero) {
    if (!data.provincia_nacimiento_id) {
      ctx.addIssue({
        path: ['provincia_nacimiento_id'], code: 'custom',
        message: 'La provincia de nacimiento es requerida',
      })
    }
    if (!data.canton_nacimiento_id) {
      ctx.addIssue({
        path: ['canton_nacimiento_id'], code: 'custom',
        message: 'El cantón de nacimiento es requerido',
      })
    }
  } else {
    if (!data.nacionalidad) {
      ctx.addIssue({
        path: ['nacionalidad'], code: 'custom',
        message: 'La nacionalidad es requerida para servidores extranjeros',
      })
    }
    if (!data.pais_origen) {
      ctx.addIssue({
        path: ['pais_origen'], code: 'custom',
        message: 'El país de origen es requerido para servidores extranjeros',
      })
    }
  }

  if (CON_PLAZO_OBLIGATORIO.includes(data.vinculo.tipo_nombramiento) && !data.vinculo.fecha_fin) {
    ctx.addIssue({
      path: ['vinculo', 'fecha_fin'], code: 'custom',
      message: 'Un contrato ocasional necesita fecha de término',
    })
  }

  // La carga inicial es para vínculos que YA existen.
  if (data.vinculo.fecha_inicio && data.vinculo.fecha_inicio > new Date().toISOString().slice(0, 10)) {
    ctx.addIssue({
      path: ['vinculo', 'fecha_inicio'], code: 'custom',
      message: 'La fecha de inicio no puede ser futura: esto es una carga histórica',
    })
  }

  if (
    data.fecha_ingreso_institucion
    && data.vinculo.fecha_inicio
    && data.fecha_ingreso_institucion > data.vinculo.fecha_inicio
  ) {
    ctx.addIssue({
      path: ['fecha_ingreso_institucion'], code: 'custom',
      message: 'El ingreso a la institución no puede ser posterior al contrato vigente',
    })
  }
})

export type VinculacionInicialFormData = z.infer<typeof vinculacionInicialSchema>
