import api from '@/lib/axios'
import type { SemanticTone } from '@/config/design.tokens'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

/** Cómo está un servidor frente a su evaluación médica ocupacional periódica. */
export type EstadoCobertura =
  | 'al_dia'
  | 'por_vencer'
  | 'vencida'
  | 'sin_evaluacion'

/** Una fila del tablero: un servidor activo, no una solicitud. */
export interface FilaCobertura {
  servidor_id:      number
  cedula:           string
  nombre_completo:  string
  unidad:           string | null
  cargo:            string | null
  ultima_solicitud_id: number | null
  /** `null` si la evaluación se cerró sin ficha: no hay certificado posible. */
  ultima_ficha_id:  number | null
  ultimo_dictamen:  string | null
  restricciones:    string | null
  /** `null` si nunca se le evaluó. */
  fecha_evaluacion: string | null
  vence_el:         string | null
  /** La solicitud viva, si ya se pidió: no hay que volver a pedirla. */
  solicitud_activa_id:            number | null
  solicitud_activa_estado:        string | null
  solicitud_activa_fecha_limite:  string | null
  estado_cobertura: EstadoCobertura
}

export type ResumenCobertura = Record<EstadoCobertura | 'total', number>

export interface CoberturaFiltros {
  page?:     number
  per_page?: number
  unidad_administrativa_id?: number
  estado_cobertura?: EstadoCobertura
  buscar?:   string
}

export const ESTADO_COBERTURA_LABELS: Record<EstadoCobertura, string> = {
  al_dia:         'Al día',
  por_vencer:     'Por vencer',
  vencida:        'Vencida',
  sin_evaluacion: 'Sin evaluación',
}

/**
 * «Sin evaluación» es peor que «vencida» para quien audita —no hay ni un
 * papel— pero una vencida es la que incumple un plazo con fecha, así que las
 * dos van en rojo y el tablero las separa por etiqueta, no por color.
 */
export const TONO_ESTADO_COBERTURA: Record<EstadoCobertura, SemanticTone> = {
  al_dia:         'success',
  por_vencer:     'warning',
  vencida:        'danger',
  sin_evaluacion: 'danger',
}

export const ESTADO_COBERTURA_FILTRO_OPTIONS = [
  { value: '',               label: 'Toda la plantilla' },
  { value: 'vencida',        label: 'Vencidas'          },
  { value: 'sin_evaluacion', label: 'Sin evaluación'    },
  { value: 'por_vencer',     label: 'Por vencer'        },
  { value: 'al_dia',         label: 'Al día'            },
]

type RespuestaCobertura = PaginatedResponse<FilaCobertura> & {
  resumen: ResumenCobertura
}

export const coberturaCertificacionService = {
  listar: (filtros?: CoberturaFiltros) =>
    api.get<ApiResponse<RespuestaCobertura>>(
      '/dispensario/certificaciones/cobertura',
      { params: filtros }
    ).then(r => r.data.datos),

  exportarExcel: (filtros?: Omit<CoberturaFiltros, 'page' | 'per_page'>) =>
    api.get<Blob>(
      '/dispensario/certificaciones/cobertura/excel',
      { params: filtros, responseType: 'blob' }
    ).then(r => r.data),
}
