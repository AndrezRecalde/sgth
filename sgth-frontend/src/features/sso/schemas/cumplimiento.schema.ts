import { z } from 'zod/v4'
import type { SemanticTone } from '@/config/design.tokens'

export const cumplimientoSchema = z.object({
  estado: z.enum(['cumple', 'no_cumple', 'en_proceso']),
  observaciones: z.string().max(2000).optional(),
})

export type CumplimientoFormData = z.infer<typeof cumplimientoSchema>

export const ESTADO_CUMPLIMIENTO_OPTIONS = [
  { value: 'cumple', label: 'Cumple' },
  { value: 'no_cumple', label: 'No cumple' },
  { value: 'en_proceso', label: 'En proceso' },
]

export const TONO_ESTADO_CUMPLIMIENTO: Record<string, SemanticTone> = {
  cumple: 'success',
  no_cumple: 'danger',
  en_proceso: 'warning',
  no_registrado: 'neutral',
}

export const ESTADO_CUMPLIMIENTO_LABELS: Record<string, string> = {
  cumple: 'Cumple',
  no_cumple: 'No cumple',
  en_proceso: 'En proceso',
  no_registrado: 'No registrado',
}
