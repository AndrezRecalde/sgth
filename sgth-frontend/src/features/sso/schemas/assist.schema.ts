import { z } from 'zod/v4'
import type { SemanticTone } from '@/config/design.tokens'

export const crearCampaniaAssistSchema = z.object({
  periodo: z.string().regex(/^\d{4}(-\d{2})?$/, 'Formato AAAA (año) o AAAA-MM (mes)'),
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
