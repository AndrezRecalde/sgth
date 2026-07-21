import { z } from 'zod/v4'

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

export const ESTADO_CUMPLIMIENTO_COLORS: Record<string, string> = {
  cumple: 'emerald',
  no_cumple: 'red',
  en_proceso: 'yellow',
  no_registrado: 'gray',
}

export const ESTADO_CUMPLIMIENTO_LABELS: Record<string, string> = {
  cumple: 'Cumple',
  no_cumple: 'No cumple',
  en_proceso: 'En proceso',
  no_registrado: 'No registrado',
}
