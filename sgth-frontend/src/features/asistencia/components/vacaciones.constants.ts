import type { EstadoVacacion, MotivoVacacion } from '@/types/api'
import type { SemanticTone } from '@/config/design.tokens'

/** Etiquetas y tonos de los estados de una solicitud de vacaciones. */
export const TONO_ESTADO: Record<EstadoVacacion, SemanticTone> = {
  pendiente: 'warning',
  aprobada:  'success',
  rechazada: 'danger',
  gozada:    'neutral',
}

export const ESTADO_LABELS: Record<EstadoVacacion, string> = {
  pendiente: 'Pendiente',
  aprobada:  'Aprobada',
  rechazada: 'Rechazada',
  gozada:    'Gozada',
}

export const MOTIVO_LABELS: Record<MotivoVacacion, string> = {
  vacaciones_anuales:        'Vacaciones anuales',
  permiso_cargo_vacaciones:  'Cargo a vacaciones',
  licencia_sin_goce:         'Licencia sin goce',
  matrimonio:                'Matrimonio',
  capacitacion:              'Capacitación',
  enfermedad:                'Enfermedad',
  maternidad:                'Maternidad',
  paternidad:                'Paternidad',
  estudios_sin_remuneracion: 'Estudios sin remuneración',
  calamidad_domestica:       'Calamidad doméstica',
  licencia_con_goce:         'Licencia con goce',
}

/** Los estados por los que se filtra, en el orden del flujo. */
export const FILTROS_ESTADO = [
  'todos',
  'pendiente',
  'aprobada',
  'rechazada',
  'gozada',
] as const
