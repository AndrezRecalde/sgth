import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoFormacion } from '@/types/api'

/** `EstadoFormacion` del backend: lo que registra el servidor espera a TH. */
export const ESTADO_FORMACION_LABELS: Record<EstadoFormacion, string> = {
  validado:    'Validado',
  por_validar: 'Por validar',
  rechazado:   'Rechazado',
}

export const TONO_ESTADO_FORMACION: Record<EstadoFormacion, SemanticTone> = {
  validado:    'success',
  por_validar: 'warning',
  rechazado:   'danger',
}
