import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoPrestamo } from '../services/tipos'

/**
 * El tono de cada estado (regla 06): solicitado espera, reservado está
 * confirmado, en curso está fuera, y lo cerrado queda neutro. Rechazado es lo
 * único que salió mal. «Vencido» no es un estado: lo marca la pantalla.
 */
export const TONO_ESTADO_PRESTAMO: Record<EstadoPrestamo, SemanticTone> = {
  solicitado:  'warning',
  reservado:   'success',
  en_curso:    'info',
  devuelto:    'neutral',
  rechazado:   'danger',
  cancelado:   'neutral',
  no_retirado: 'neutral',
}

export const ESTADOS_PRESTAMO: { value: EstadoPrestamo; label: string }[] = [
  { value: 'solicitado',  label: 'Solicitado' },
  { value: 'reservado',   label: 'Reservado' },
  { value: 'en_curso',    label: 'En curso' },
  { value: 'devuelto',    label: 'Devuelto' },
  { value: 'rechazado',   label: 'Rechazado' },
  { value: 'cancelado',   label: 'Cancelado' },
  { value: 'no_retirado', label: 'No retirado' },
]

export const CONDICION_LABELS: Record<'bueno' | 'regular' | 'malo', string> = {
  bueno: 'Bueno', regular: 'Regular', malo: 'Malo',
}
