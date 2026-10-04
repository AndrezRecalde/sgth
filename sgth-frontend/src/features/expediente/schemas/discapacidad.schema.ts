import { z } from 'zod/v4'
import { PORCENTAJE_MINIMO_DISCAPACIDAD } from '../utils/discapacidad'

const RANGO = `El porcentaje de discapacidad va del ${PORCENTAJE_MINIMO_DISCAPACIDAD} % al 100 %`

/** Los valores de `App\Enums\TipoDiscapacidad`. */
export const TIPOS_DISCAPACIDAD = [
  'fisica', 'sensorial', 'intelectual', 'psicosocial', 'visceral', 'multiple',
] as const

/** La del servidor: el carné CONADIS es obligatorio. */
export const discapacidadSchema = z.object({
  tipo_discapacidad: z.enum(TIPOS_DISCAPACIDAD, { error: 'Elija el tipo de discapacidad' }),
  porcentaje: z.number({ error: 'Indique el porcentaje' })
    .min(PORCENTAJE_MINIMO_DISCAPACIDAD, RANGO)
    .max(100, RANGO),
  numero_carnet_conadis: z.string().min(1, 'El número de carnet es requerido'),
})

/**
 * La de una carga familiar: el carné es opcional, porque Talento Humano no
 * siempre lo tiene a mano para un familiar.
 */
export const discapacidadCargaSchema = discapacidadSchema.extend({
  numero_carnet_conadis: z.string().optional().nullable(),
})

export type DiscapacidadFormData = z.infer<typeof discapacidadCargaSchema>
