import type { SemanticTone } from '@/config/design.tokens'

// Los estados de EstadoConvocatoria (backend). Antes había `en_proceso` y
// `cerrada`, que no existen, y faltaba `cancelada`. `en_evaluacion` solo lo
// tienen datos antiguos: ninguna acción lleva a él.
export const ESTADO_CONVOCATORIA_OPTIONS = [
  { value: 'borrador',              label: 'Borrador'              },
  { value: 'publicada',             label: 'Publicada'             },
  { value: 'en_evaluacion',         label: 'En evaluación'         },
  { value: 'en_evaluacion_medica',  label: 'En evaluación médica'  },
  { value: 'finalizada',            label: 'Finalizada'            },
  { value: 'desierta',              label: 'Desierta'              },
  { value: 'cancelada',             label: 'Cancelada'             },
]

export const TONO_CONVOCATORIA: Record<string, SemanticTone> = {
  borrador:             'neutral',
  publicada:            'info',
  en_evaluacion:        'info',
  en_evaluacion_medica: 'info',
  finalizada:           'success',
  desierta:             'danger',
  cancelada:            'neutral',
}

/** El mismo estado se ve en el detalle de la convocatoria y en el ranking. */
export const TONO_POSTULANTE: Record<string, SemanticTone> = {
  inscrito:           'neutral',
  en_evaluacion:      'info',
  aprobado:           'success',
  reprobado:          'danger',
  descalificado:      'danger',
  seleccionado:       'success',
  ganador_potencial:  'info',
  no_seleccionado:    'neutral',
  lista_espera:       'warning',
  incorporado:        'success',
}

export const TIPO_CONVOCATORIA_OPTIONS = [
  { value: 'interna', label: 'Interna' },
  { value: 'externa', label: 'Externa' },
  { value: 'mixta',   label: 'Mixta'   },
]

export const ESTADO_POSTULANTE_OPTIONS = [
  { value: 'inscrito',           label: 'Inscrito'            },
  { value: 'en_evaluacion',      label: 'En evaluación'       },
  { value: 'aprobado',           label: 'Aprobado'            },
  { value: 'reprobado',          label: 'Reprobado'           },
  { value: 'descalificado',      label: 'Descalificado'       },
  { value: 'seleccionado',       label: 'Seleccionado'        },
  { value: 'ganador_potencial',  label: 'En evaluación médica'},
  { value: 'no_seleccionado',    label: 'No seleccionado'     },
  { value: 'lista_espera',       label: 'Lista de espera'     },
  { value: 'incorporado',        label: 'Incorporado'         },
]
