import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type DenticionTipo = 'permanente' | 'temporal'

export type CondicionPiezaDental =
  | 'sano' | 'cariado' | 'obturado' | 'ausente'
  | 'corona' | 'protesis' | 'sellante' | 'fracturada'
  | 'en_tratamiento' | 'a_extraer' | 'endodoncia'

export type ProcedimientoOdontologico =
  | 'examen_inicial' | 'profilaxis' | 'aplicacion_fluor' | 'sellante'
  | 'resina' | 'amalgama' | 'endodoncia' | 'extraccion' | 'corona'
  | 'protesis_parcial' | 'protesis_total' | 'curetaje'
  | 'exodoncia_quirurgica' | 'pulpotomia' | 'recubrimiento_pulpar'
  | 'ferulizacion' | 'blanqueamiento' | 'control_ortodoncia'
  | 'muda_natural' | 'otro'

export interface OdontogramaProcedimientoDetalle {
  id:                    number
  odontograma_pieza_id:  number
  consulta_medica_id?:   number | null
  procedimiento:         ProcedimientoOdontologico
  superficie?:           string | null
  observaciones?:        string | null
  fecha:                 string
  created_at:            string
  anulado_en?:           string | null
  motivo_anulacion?:     string | null
  realizado_por?: {
    id:              number
    usuario_ti?:     string
    nombre_completo?: string
  } | null
  anulado_por?: {
    id:              number
    usuario_ti?:     string
    nombre_completo?: string
  } | null
}

export interface OdontogramaPieza {
  id:            number
  numero_pieza:  number
  denticion:     DenticionTipo
  condicion:     CondicionPiezaDental
  procedimientos?: OdontogramaProcedimientoDetalle[]
}

export interface Odontograma {
  id:                  number
  historia_clinica_id: number
  piezas:              OdontogramaPieza[]
}

export interface RegistrarProcedimientoData {
  odontograma_pieza_id: number
  consulta_medica_id?:  number | null
  procedimiento:        ProcedimientoOdontologico
  superficie?:          string | null
  observaciones?:       string | null
  fecha?:               string | null
}

export interface AnularProcedimientoData {
  motivo_anulacion:    string
  consulta_medica_id?: number | null
}

export const CONDICION_LABELS: Record<CondicionPiezaDental, string> = {
  sano:           'Sano',
  cariado:        'Cariado',
  obturado:       'Obturado',
  ausente:        'Ausente',
  corona:         'Corona',
  protesis:       'Prótesis',
  sellante:       'Sellante',
  fracturada:     'Fracturada',
  en_tratamiento: 'En tratamiento',
  a_extraer:      'A extraer',
  endodoncia:     'Endodoncia',
}

export const CONDICION_COLORS: Record<CondicionPiezaDental, string> = {
  sano:           'emerald',
  cariado:        'red',
  obturado:       'blue',
  ausente:        'gray',
  corona:         'yellow',
  protesis:       'grape',
  sellante:       'cyan',
  fracturada:     'orange',
  en_tratamiento: 'violet',
  a_extraer:      'red',
  endodoncia:     'indigo',
}

export const PROCEDIMIENTO_OPTIONS: { value: ProcedimientoOdontologico; label: string }[] = [
  { value: 'examen_inicial',        label: 'Examen inicial'         },
  { value: 'profilaxis',            label: 'Profilaxis'             },
  { value: 'aplicacion_fluor',      label: 'Aplicación de flúor'    },
  { value: 'sellante',              label: 'Sellante'                },
  { value: 'resina',                label: 'Resina'                  },
  { value: 'amalgama',              label: 'Amalgama'                },
  { value: 'endodoncia',            label: 'Endodoncia'              },
  { value: 'extraccion',            label: 'Extracción'              },
  { value: 'corona',                label: 'Corona'                  },
  { value: 'protesis_parcial',      label: 'Prótesis parcial'       },
  { value: 'protesis_total',        label: 'Prótesis total'         },
  { value: 'curetaje',              label: 'Curetaje'                },
  { value: 'exodoncia_quirurgica',  label: 'Exodoncia quirúrgica'   },
  { value: 'pulpotomia',            label: 'Pulpotomía'             },
  { value: 'recubrimiento_pulpar',  label: 'Recubrimiento pulpar'    },
  { value: 'ferulizacion',          label: 'Ferulización'           },
  { value: 'blanqueamiento',        label: 'Blanqueamiento'          },
  { value: 'control_ortodoncia',    label: 'Control de ortodoncia'   },
  { value: 'muda_natural',          label: 'Muda natural (diente de leche caído)' },
  { value: 'otro',                  label: 'Otro'                    },
]

export const SUPERFICIE_OPTIONS = [
  { value: 'mesial',            label: 'Mesial'             },
  { value: 'distal',            label: 'Distal'             },
  { value: 'oclusal',           label: 'Oclusal'            },
  { value: 'vestibular',        label: 'Vestibular'         },
  { value: 'lingual_palatino',  label: 'Lingual/Palatino'   },
]

export const odontogramaService = {
  obtenerPorHistoriaClinica: (historiaClinicaId: number) =>
    api.get<ApiResponse<Odontograma>>(
      `/dispensario/odontograma/historia-clinica/${historiaClinicaId}`
    ).then(r => r.data.datos),

  registrarProcedimiento: (data: RegistrarProcedimientoData) =>
    api.post<ApiResponse<OdontogramaProcedimientoDetalle>>(
      '/dispensario/odontograma/procedimientos', data
    ).then(r => r.data.datos),

  historialPieza: (piezaId: number) =>
    api.get<ApiResponse<OdontogramaProcedimientoDetalle[]>>(
      `/dispensario/odontograma/piezas/${piezaId}/historial`
    ).then(r => r.data.datos ?? []),

  anularProcedimiento: (id: number, data: AnularProcedimientoData) =>
    api.patch<ApiResponse<OdontogramaProcedimientoDetalle>>(
      `/dispensario/odontograma/procedimientos/${id}/anular`, data
    ).then(r => r.data.datos),
}
