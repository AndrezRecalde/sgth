import type { SemanticTone } from '@/config/design.tokens'
import type { ResultadoConstatacion } from '../services/constatacionesService'

/**
 * El resultado de cada equipo en una constatación (regla 06): en su lugar o
 * registrado en sitio, bien; de otra ubicación, a revisar; no encontrado o de
 * baja, mal; pendiente, todavía nada.
 */
export const RESULTADO_CONSTATACION: Record<ResultadoConstatacion, { etiqueta: string; tono: SemanticTone }> = {
  pendiente:      { etiqueta: 'Pendiente', tono: 'neutral' },
  en_su_lugar:    { etiqueta: 'En su lugar', tono: 'success' },
  nuevo:          { etiqueta: 'Registrado en sitio', tono: 'success' },
  otra_ubicacion: { etiqueta: 'De otra ubicación', tono: 'warning' },
  de_baja:        { etiqueta: 'Estaba dado de baja', tono: 'danger' },
  no_encontrado:  { etiqueta: 'No encontrado', tono: 'danger' },
}
