import { z } from 'zod/v4'

export const riesgoLaboralSchema = z.object({
  puesto_id: z.number({ error: 'Seleccione el puesto' }).min(1, 'Seleccione el puesto'),
  factor_riesgo_id: z.number({ error: 'Seleccione el factor de riesgo' }).min(1, 'Seleccione el factor de riesgo'),
  descripcion: z.string().min(5, 'Mínimo 5 caracteres').max(2000),
  nivel_deficiencia: z.enum(['muy_deficiente', 'deficiente', 'mejorable', 'aceptable']),
  nivel_exposicion: z.enum(['continuada', 'frecuente', 'ocasional', 'esporadica']),
  nivel_consecuencias: z.enum(['mortal_catastrofico', 'muy_grave', 'grave', 'leve']),
  medidas_preventivas: z.string().max(2000).optional(),
  estado: z.boolean(),
})

export type RiesgoLaboralFormData = z.infer<typeof riesgoLaboralSchema>

// ── NTP 330 (INSHT) — Sistema simplificado de evaluación de riesgos de accidente ──

export const NIVEL_DEFICIENCIA_OPTIONS = [
  { value: 'muy_deficiente', label: 'Muy deficiente' },
  { value: 'deficiente', label: 'Deficiente' },
  { value: 'mejorable', label: 'Mejorable' },
  { value: 'aceptable', label: 'Aceptable' },
]

export const NIVEL_EXPOSICION_OPTIONS = [
  { value: 'continuada', label: 'Continuada' },
  { value: 'frecuente', label: 'Frecuente' },
  { value: 'ocasional', label: 'Ocasional' },
  { value: 'esporadica', label: 'Esporádica' },
]

export const NIVEL_CONSECUENCIAS_OPTIONS = [
  { value: 'mortal_catastrofico', label: 'Mortal o catastrófico' },
  { value: 'muy_grave', label: 'Muy grave' },
  { value: 'grave', label: 'Grave' },
  { value: 'leve', label: 'Leve' },
]

const VALOR_ND: Record<string, number> = {
  muy_deficiente: 10, deficiente: 6, mejorable: 2, aceptable: 0,
}
const VALOR_NE: Record<string, number> = {
  continuada: 4, frecuente: 3, ocasional: 2, esporadica: 1,
}
const VALOR_NC: Record<string, number> = {
  mortal_catastrofico: 100, muy_grave: 60, grave: 25, leve: 10,
}

export interface ResultadoNtp330 {
  nivelProbabilidad: number
  nivelRiesgo: number
  nivelIntervencion: 'i' | 'ii' | 'iii' | 'iv'
}

export const NIVEL_INTERVENCION_LABELS: Record<string, string> = {
  i: 'I — Situación crítica, corrección urgente',
  ii: 'II — Corregir y adoptar medidas de control',
  iii: 'III — Mejorar si es posible',
  iv: 'IV — No intervenir, salvo análisis más preciso',
}

export const NIVEL_INTERVENCION_COLORS: Record<string, string> = {
  i: 'red',
  ii: 'orange',
  iii: 'yellow',
  iv: 'emerald',
}

/** Réplica en cliente del cálculo NTP 330 del backend, solo para previsualización antes de guardar. */
export function calcularNtp330(
  nivelDeficiencia: string, nivelExposicion: string, nivelConsecuencias: string,
): ResultadoNtp330 {
  const nd = VALOR_ND[nivelDeficiencia] ?? 0
  const ne = VALOR_NE[nivelExposicion] ?? 0
  const nc = VALOR_NC[nivelConsecuencias] ?? 0

  const nivelProbabilidad = nd * ne
  const nivelRiesgo = nivelProbabilidad * nc

  const nivelIntervencion: ResultadoNtp330['nivelIntervencion'] =
    nivelRiesgo >= 600 ? 'i'
      : nivelRiesgo >= 150 ? 'ii'
        : nivelRiesgo >= 40 ? 'iii'
          : 'iv'

  return { nivelProbabilidad, nivelRiesgo, nivelIntervencion }
}
