import { z } from 'zod/v4'
import { esFechaFutura } from './sumario.schema'

/**
 * Solicitud de visto bueno ante el Inspector del Trabajo. Las reglas son las
 * de `StoreVistoBuenoRequest`.
 */

const HECHOS_MAX = 5000
const TRAMITE_MAX = 50
const INSPECTORIA_MAX = 150

export const vistoBuenoSchema = z.object({
  servidor_id: z.number({ message: 'Seleccione al trabajador' }),
  causal: z.enum(
    [
      'faltas_puntualidad_asistencia',
      'indisciplina_desobediencia',
      'falta_probidad',
      'injurias_graves',
      'ineptitud_manifiesta',
      'denuncia_injustificada_iess',
      'incumplimiento_seguridad',
    ],
    { message: 'Indique la causal del Art. 172 del Código del Trabajo' },
  ),
  hechos: z.string()
    .min(5, 'Relate el fundamento de hecho de la solicitud')
    .max(HECHOS_MAX, `Máximo ${HECHOS_MAX} caracteres`),
  fecha_solicitud: z.string()
    .min(1, 'Indique la fecha de presentación de la solicitud')
    .refine((f) => !esFechaFutura(f), 'La solicitud no puede presentarse con una fecha futura'),
  numero_tramite_mdt: z.string().max(TRAMITE_MAX, `Máximo ${TRAMITE_MAX} caracteres`),
  inspectoria: z.string().max(INSPECTORIA_MAX, `Máximo ${INSPECTORIA_MAX} caracteres`),
})

export type VistoBuenoFormValues = z.infer<typeof vistoBuenoSchema>
