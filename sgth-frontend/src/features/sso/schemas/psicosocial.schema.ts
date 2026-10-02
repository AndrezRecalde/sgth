import { z } from 'zod/v4'
import type { SemanticTone } from '@/config/design.tokens'
import { AYUDA_PERIODO, PATRON_PERIODO } from '../constants/periodo'

export const crearCampaniaPsicosocialSchema = z.object({
  periodo: z.string().regex(PATRON_PERIODO, AYUDA_PERIODO),
  unidad_administrativa_id: z.number().nullable().optional(),
  fecha_apertura: z.string().min(1, 'Requerido'),
  fecha_cierre: z.string().nullable().optional(),
})
  // El backend la exige (`after_or_equal:fecha_apertura`) y aquí no se
  // comprobaba, así que una campaña con el cierre antes de la apertura
  // viajaba al servidor y volvía como una notificación genérica, sin marcar
  // el campo.
  //
  // `when: () => true`: en Zod v4 un refine sobre el objeto no corre si ya hay
  // otro error, y entonces el aviso del rango no aparecía hasta arreglar lo
  // demás y reintentar.
  .refine(
    (datos) => !datos.fecha_cierre || datos.fecha_cierre >= datos.fecha_apertura,
    {
      path: ['fecha_cierre'],
      message: 'El cierre no puede ser anterior a la apertura',
      when: () => true,
    },
  )

export type CrearCampaniaPsicosocialFormData = z.infer<typeof crearCampaniaPsicosocialSchema>

export const NIVEL_RIESGO_PSICOSOCIAL_LABELS: Record<string, string> = {
  bajo: 'Riesgo bajo',
  medio: 'Riesgo medio',
  alto: 'Riesgo alto',
}

export const TONO_RIESGO_PSICOSOCIAL: Record<string, SemanticTone> = {
  bajo: 'success',
  medio: 'warning',
  alto: 'danger',
}

export const OPCIONES_LIKERT_PSICOSOCIAL = [
  { value: 4, label: 'Completamente de acuerdo' },
  { value: 3, label: 'Parcialmente de acuerdo' },
  { value: 2, label: 'Poco de acuerdo' },
  { value: 1, label: 'En desacuerdo' },
]
