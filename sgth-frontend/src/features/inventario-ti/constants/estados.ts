import type { SemanticTone } from '@/config/design.tokens'
import type { CondicionFisica, EstadoBien } from '../services/tipos'

/**
 * El tono de cada estado de un equipo (regla 06): en uso y disponible están
 * bien; en mantenimiento o en garantía, en curso; extraviado, mal; de baja,
 * fuera del juego.
 */
export const TONO_ESTADO_BIEN: Record<EstadoBien, SemanticTone> = {
  en_uso:           'success',
  disponible:       'success',
  en_mantenimiento: 'info',
  en_garantia:      'info',
  extraviado:       'danger',
  dado_de_baja:     'neutral',
}

export const ESTADO_BIEN_LABELS: Record<EstadoBien, string> = {
  en_uso:           'En uso',
  disponible:       'Disponible',
  en_mantenimiento: 'En mantenimiento',
  en_garantia:      'En garantía',
  extraviado:       'Extraviado',
  dado_de_baja:     'Dado de baja',
}

export const TONO_CONDICION: Record<CondicionFisica, SemanticTone> = {
  bueno:   'success',
  regular: 'warning',
  malo:    'danger',
}

export const CONDICION_LABELS: Record<CondicionFisica, string> = {
  bueno: 'Bueno', regular: 'Regular', malo: 'Malo',
}

/** Los estados que TIC pone a mano. En uso y disponible salen de la custodia. */
export const ESTADOS_MANUALES: { value: EstadoBien; label: string }[] = [
  { value: 'disponible',       label: 'En servicio (según su custodia)' },
  { value: 'en_mantenimiento', label: 'En mantenimiento' },
  { value: 'en_garantia',      label: 'En garantía con el proveedor' },
  { value: 'extraviado',       label: 'Extraviado' },
]
