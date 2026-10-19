import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoSolicitudCarga } from '@/types/api'

/**
 * Las condiciones de salud del familiar no se piden desde el Portal: se
 * registran en persona (D12 del diseño de cargas desde el Portal). El aviso
 * sale en la pestaña, en el formulario de registro y al enviarlo.
 */
export const AVISO_CONDICIONES_DE_SALUD =
  'Si su familiar tiene una discapacidad o una enfermedad catastrófica, acérquese a Talento Humano '
  + 'con el carnet del CONADIS o el diagnóstico para que la registren en su expediente.'

export const TONO_SOLICITUD_CARGA: Record<EstadoSolicitudCarga, SemanticTone> = {
  pendiente: 'warning',
  validada:  'success',
  rechazada: 'danger',
  retirada:  'neutral',
}
