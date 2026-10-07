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

/**
 * Las mismas etiquetas que `TipoPermiso::etiqueta()` en el backend, que es lo
 * que sale en el PDF y en el consolidado. Aquí decía «Enfermedad» y
 * «Calamidad», así que el mismo permiso se llamaba distinto en pantalla y en
 * papel.
 */
export const TIPO_LABELS: Record<string, string> = {
  personal:   'Personal',
  oficial:    'Oficial',
  enfermedad: 'Por enfermedad',
  calamidad:  'Calamidad doméstica',
}

/** Los tipos que se ofrecen al registrar un permiso. */
export const TIPO_OPCIONES = [
  { value: 'personal',   label: 'Personal (máx. 4 horas)' },
  { value: 'oficial',    label: 'Oficial' },
  { value: 'enfermedad', label: 'Por enfermedad' },
  { value: 'calamidad',  label: 'Calamidad doméstica' },
]

/**
 * Los tipos por los que se filtra el consolidado.
 *
 * `TIPO_OPCIONES` no vale aquí: lleva «(máx. 4 horas)» en Personal, que es una
 * regla de quien registra, no de quien consulta un informe. Pero vive junto a
 * las demás y no dentro del filtro, que es donde estaba: el mismo tipo llegó a
 * llamarse de cuatro formas distintas entre el registro, el consolidado y el
 * PDF.
 */
export const TIPO_OPCIONES_CONSOLIDADO = Object.entries(TIPO_LABELS).map(
  ([value, label]) => ({ value, label }),
)

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

/** Los que valida Trabajo Social una vez confirmados (`PermisoService::esDeTrabajoSocial`). */
export const TIPOS_TRABAJO_SOCIAL = ['enfermedad', 'calamidad']

/** Enfermedad y calamidad se justifican después: nunca llevan fecha futura. */
export const TIPOS_RETROACTIVOS = ['enfermedad', 'calamidad']
