import type { SemanticTone } from '@/config/design.tokens'

/**
 * Etiquetas y tonos de los estados de un permiso.
 *
 * `rechazado` y `falta_injustificada` faltaban en los dos mapas, así que se
 * pintaban en crudo y sin color. El segundo era invisible en la práctica
 * porque el job que lo marca no estaba programado; ahora sí lo está, y es
 * justo el estado que a Talento Humano le interesa perseguir.
 */
export const TONO_ESTADO: Record<string, SemanticTone> = {
  pendiente:               'warning',
  activo:                  'info',
  validado_trabajo_social: 'success',
  anulado:                 'neutral',
  rechazado:               'danger',
  falta_injustificada:     'danger',
}

export const ESTADO_LABELS: Record<string, string> = {
  pendiente:               'Pendiente',
  activo:                  'Activo',
  validado_trabajo_social: 'Validado TS',
  anulado:                 'Anulado',
  rechazado:               'Rechazado',
  falta_injustificada:     'Falta injustificada',
}

export const TIPO_LABELS: Record<string, string> = {
  personal:   'Personal',
  oficial:    'Oficial',
  enfermedad: 'Enfermedad',
  calamidad:  'Calamidad',
}

/** Los estados por los que se filtra, en el orden del flujo. */
export const FILTROS_ESTADO = [
  'todos',
  'pendiente',
  'activo',
  'validado_trabajo_social',
  'rechazado',
  'falta_injustificada',
  'anulado',
] as const

/** Un permiso ya recibido por Recepción: se puede revertir, no rechazar. */
export const ESTADOS_CONFIRMADOS = ['activo', 'validado_trabajo_social']

/** Enfermedad y calamidad se justifican después: nunca llevan fecha futura. */
export const TIPOS_RETROACTIVOS = ['enfermedad', 'calamidad']
