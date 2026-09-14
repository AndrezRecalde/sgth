import type { SemanticTone } from '@/config/design.tokens'

/** Tono del estado de una nómina; lo comparten el listado y el detalle. */
export const TONO_NOMINA: Record<string, SemanticTone> = {
  borrador:      'neutral',
  en_proceso:    'info',
  cerrada:       'warning',
  contabilizada: 'info',
  pagada:        'success',
}
