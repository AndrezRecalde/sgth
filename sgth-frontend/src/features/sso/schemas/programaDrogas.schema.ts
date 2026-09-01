import { z } from 'zod/v4'
import type { SemanticTone } from '@/config/design.tokens'

export const actividadProgramaSchema = z.object({
  fase: z.enum([
    'fase_1_preparacion',
    'fase_2_equipo_multidisciplinario',
    'fase_3_socializacion',
    'fase_4_diagnostico',
    'fase_5_actuacion',
    'fase_6_seguimiento',
  ]),
  nombre: z.string().min(1, 'Requerido').max(255),
  descripcion: z.string().max(3000).optional(),
})

export type ActividadProgramaFormData = z.infer<typeof actividadProgramaSchema>

export const seguimientoProgramaSchema = z.object({
  estado: z.enum(['pendiente', 'en_proceso', 'ejecutada', 'no_ejecutada']),
  fecha_ejecucion: z.string().nullable().optional(),
  observaciones: z.string().max(2000).optional(),
})

export type SeguimientoProgramaFormData = z.infer<typeof seguimientoProgramaSchema>

export const FASE_PROGRAMA_DROGAS_OPTIONS = [
  { value: 'fase_1_preparacion', label: 'Fase 1: Preparación' },
  { value: 'fase_2_equipo_multidisciplinario', label: 'Fase 2: Equipo multidisciplinario' },
  { value: 'fase_3_socializacion', label: 'Fase 3: Socialización' },
  { value: 'fase_4_diagnostico', label: 'Fase 4: Diagnóstico' },
  { value: 'fase_5_actuacion', label: 'Fase 5: Actuación' },
  { value: 'fase_6_seguimiento', label: 'Fase 6: Seguimiento' },
]

export const ESTADO_ACTIVIDAD_PROGRAMA_OPTIONS = [
  { value: 'pendiente', label: 'Pendiente' },
  { value: 'en_proceso', label: 'En proceso' },
  { value: 'ejecutada', label: 'Ejecutada' },
  { value: 'no_ejecutada', label: 'No ejecutada' },
]

export const ESTADO_ACTIVIDAD_PROGRAMA_LABELS: Record<string, string> = {
  pendiente: 'Pendiente',
  en_proceso: 'En proceso',
  ejecutada: 'Ejecutada',
  no_ejecutada: 'No ejecutada',
}

export const TONO_ACTIVIDAD_PROGRAMA: Record<string, SemanticTone> = {
  pendiente: 'neutral',
  en_proceso: 'warning',
  ejecutada: 'success',
  no_ejecutada: 'danger',
}
