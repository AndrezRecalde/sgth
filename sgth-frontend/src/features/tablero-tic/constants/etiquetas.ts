import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoEnCurso, TramoAntiguedad } from '../services/tableroService'

/** El estado de un ticket en curso, como lo lee TIC (no el solicitante). */
export const ESTADO_EN_CURSO: Record<EstadoEnCurso, { etiqueta: string; tono: SemanticTone }> = {
  abierto:           { etiqueta: 'Sin tomar', tono: 'info' },
  asignado:          { etiqueta: 'Asignados', tono: 'info' },
  en_proceso:        { etiqueta: 'En proceso', tono: 'info' },
  pendiente_usuario: { etiqueta: 'Esperando al usuario', tono: 'warning' },
}

/**
 * Los años desde la compra. Pasados los cinco, la mayoría de los equipos ya
 * cumplió la vida útil de su tipo; pasados los ocho, es candidato a baja.
 */
export const TRAMO_ANTIGUEDAD: Record<TramoAntiguedad, { etiqueta: string; tono: SemanticTone }> = {
  menos_de_3: { etiqueta: 'Menos de 3 años', tono: 'success' },
  de_3_a_5:   { etiqueta: 'De 3 a 5 años', tono: 'info' },
  de_5_a_8:   { etiqueta: 'De 5 a 8 años', tono: 'warning' },
  mas_de_8:   { etiqueta: 'Más de 8 años', tono: 'danger' },
  sin_fecha:  { etiqueta: 'Sin fecha de compra', tono: 'neutral' },
}
