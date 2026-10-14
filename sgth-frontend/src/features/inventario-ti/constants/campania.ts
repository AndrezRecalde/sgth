import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoEquipoCampania, SituacionCampania } from '../services/campaniasService'

/**
 * Cómo va una campaña (regla 06): en curso, información; atrasada, a revisar;
 * programada o cerrada, nada que hacer todavía o ya.
 */
export const SITUACION_CAMPANIA: Record<SituacionCampania, { etiqueta: string; tono: SemanticTone }> = {
  programada: { etiqueta: 'Programada', tono: 'neutral' },
  en_curso:   { etiqueta: 'En curso', tono: 'info' },
  atrasada:   { etiqueta: 'Atrasada', tono: 'warning' },
  cerrada:    { etiqueta: 'Cerrada', tono: 'neutral' },
}

/** Cada equipo de la campaña: hecho, bien; omitido, a revisar; no realizado al cerrar, mal. */
export const ESTADO_EQUIPO_CAMPANIA: Record<EstadoEquipoCampania, { etiqueta: string; tono: SemanticTone }> = {
  pendiente:    { etiqueta: 'Pendiente', tono: 'neutral' },
  hecho:        { etiqueta: 'Hecho', tono: 'success' },
  omitido:      { etiqueta: 'Omitido', tono: 'warning' },
  no_realizado: { etiqueta: 'No realizado', tono: 'danger' },
}
