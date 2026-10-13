import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoSla, EstadoTicket, PrioridadTicket } from '../services/tipos'

/**
 * El tono de cada estado (regla 06): lo que está en manos de TIC informa, lo
 * que espera al usuario advierte, lo resuelto está bien y lo cerrado queda
 * neutro.
 */
export const TONO_ESTADO_TICKET: Record<EstadoTicket, SemanticTone> = {
  abierto:           'info',
  asignado:          'info',
  en_proceso:        'info',
  pendiente_usuario: 'warning',
  resuelto:          'success',
  cerrado:           'neutral',
  anulado:           'neutral',
}

/** Cómo se lee el estado en el Portal, desde quien abrió el ticket. */
export const ESTADO_PARA_EL_SOLICITANTE: Record<EstadoTicket, string> = {
  abierto:           'Recibido',
  asignado:          'Asignado',
  en_proceso:        'En atención',
  pendiente_usuario: 'Esperando su respuesta',
  resuelto:          'Resuelto: confírmelo',
  cerrado:           'Cerrado',
  anulado:           'Anulado',
}

export const TONO_PRIORIDAD: Record<PrioridadTicket, SemanticTone> = {
  baja: 'neutral', media: 'info', alta: 'warning', urgente: 'danger',
}

export const SLA_LABELS: Record<EstadoSla, { etiqueta: string; tono: SemanticTone }> = {
  en_plazo:   { etiqueta: 'En plazo', tono: 'success' },
  por_vencer: { etiqueta: 'Por vencer', tono: 'warning' },
  vencido:    { etiqueta: 'Vencido', tono: 'danger' },
  pausado:    { etiqueta: 'En pausa', tono: 'neutral' },
  cumplido:   { etiqueta: 'Cumplido', tono: 'success' },
  incumplido: { etiqueta: 'Fuera de plazo', tono: 'danger' },
}

/** Los estados que el solicitante filtra en «Mis tickets». */
export const FILTROS_MIS_TICKETS: { value: 'en_curso' | 'resueltos' | 'cerrados'; label: string }[] = [
  { value: 'en_curso', label: 'En curso' },
  { value: 'resueltos', label: 'Por confirmar' },
  { value: 'cerrados', label: 'Cerrados' },
]

/** Los archivos que acepta la conversación (`config/helpdesk.php`). */
export const ADJUNTOS_TICKET = {
  mimes: [
    'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  ],
  maxMb: 10,
  formatos: 'Imagen, PDF, texto, Word o Excel — máx. 10 MB',
}
