import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoInstruccion } from '../services/brechasDePerfilService'

/** Cómo está la instrucción de cada servidor frente al perfil de su puesto (P7, PR 17). */
export const ESTADOS_INSTRUCCION: { value: EstadoInstruccion; label: string; tone: SemanticTone }[] = [
  { value: 'no_cumple',      label: 'No cumple',       tone: 'warning' },
  { value: 'por_revisar',    label: 'Por revisar',     tone: 'info' },
  { value: 'cumple',         label: 'Cumple',          tone: 'success' },
  { value: 'sin_requisitos', label: 'Sin requisitos',  tone: 'neutral' },
]

export function estadoInstruccion(estado: EstadoInstruccion) {
  return ESTADOS_INSTRUCCION.find((e) => e.value === estado) ?? ESTADOS_INSTRUCCION[0]
}
