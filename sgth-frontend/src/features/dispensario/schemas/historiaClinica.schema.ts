import { z } from 'zod/v4'

/**
 * Lo que se agrega a la historia clínica desde el panel de la consulta:
 * alergias y antecedentes.
 *
 * Las reglas son las de `StoreAlergiaPacienteRequest` y
 * `StoreAntecedentePacienteRequest`. Antes el formulario solo exigía que los
 * campos no estuvieran vacíos, así que lo que el servidor rechazaba —una
 * descripción de más de 255 caracteres, un año fuera de rango— volvía como un
 * 422 suelto, sin señalar el campo que lo causó.
 */

export const TIPO_ALERGIA_OPTIONS = [
  { value: 'medicamento', label: 'Medicamento' },
  { value: 'alimento',    label: 'Alimento'    },
  { value: 'ambiental',   label: 'Ambiental'   },
  { value: 'otro',        label: 'Otro'         },
]

export const SEVERIDAD_OPTIONS = [
  { value: 'leve',     label: 'Leve'     },
  { value: 'moderada', label: 'Moderada' },
  { value: 'grave',    label: 'Grave'    },
]

export const TIPO_ANTECEDENTE_OPTIONS = [
  { value: 'quirurgico',   label: 'Quirúrgico'   },
  { value: 'patologico',   label: 'Patológico'   },
  { value: 'traumatico',   label: 'Traumático'   },
  { value: 'ginecologico', label: 'Ginecológico' },
  { value: 'otro',         label: 'Otro'          },
]

export const alergiaSchema = z.object({
  tipo: z.enum(['medicamento', 'alimento', 'ambiental', 'otro'], {
    message: 'Seleccione el tipo de alergia',
  }),
  descripcion: z
    .string()
    .min(1, 'Describa la alergia')
    .max(255, 'Máximo 255 caracteres'),
  severidad: z.enum(['leve', 'moderada', 'grave'], {
    message: 'Seleccione la severidad',
  }),
  observacion: z.string(),
})

export type AlergiaFormData = z.infer<typeof alergiaSchema>

/** El año del antecedente: ni antes de 1900 ni en el futuro. */
const ANIO_MINIMO = 1900

export const antecedenteSchema = z.object({
  // El familiar no elige tipo —siempre es `familiar`—, así que aquí caben los
  // seis valores que acepta el servidor y el formulario solo enseña los cinco
  // personales.
  tipo: z.enum(
    ['quirurgico', 'patologico', 'traumatico', 'ginecologico', 'familiar', 'otro'],
    { message: 'Seleccione el tipo de antecedente' },
  ),
  descripcion: z.string().min(5, 'Mínimo 5 caracteres'),
  fecha_aproximada: z
    .number()
    .int('El año debe ser un número entero')
    .min(ANIO_MINIMO, `No puede ser anterior a ${ANIO_MINIMO}`)
    .max(new Date().getFullYear(), 'No puede ser un año futuro')
    .nullable(),
})

export type AntecedenteFormData = z.infer<typeof antecedenteSchema>
