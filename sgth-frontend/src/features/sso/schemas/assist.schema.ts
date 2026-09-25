import { z } from 'zod/v4'
import type { SemanticTone } from '@/config/design.tokens'
import { AYUDA_PERIODO, PATRON_PERIODO } from '../constants/periodo'

export const crearCampaniaAssistSchema = z.object({
  periodo: z.string().regex(PATRON_PERIODO, AYUDA_PERIODO),
  unidad_administrativa_id: z.number().nullable().optional(),
  fecha_apertura: z.string().min(1, 'Requerido'),
  fecha_cierre: z.string().nullable().optional(),
})

export type CrearCampaniaAssistFormData = z.infer<typeof crearCampaniaAssistSchema>

export const NIVEL_RIESGO_ASSIST_LABELS: Record<string, string> = {
  bajo: 'Riesgo bajo',
  moderado: 'Riesgo moderado',
  alto: 'Riesgo alto',
}

export const TONO_RIESGO_ASSIST: Record<string, SemanticTone> = {
  bajo: 'success',
  moderado: 'warning',
  alto: 'danger',
}
