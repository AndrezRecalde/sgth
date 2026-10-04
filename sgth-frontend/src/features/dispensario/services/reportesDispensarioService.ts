import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/** Los filtros que un reporte puede ofrecer, además del período. */
export type FiltroReporte = 'profesional' | 'especialidad' | 'tipo_paciente' | 'unidad'

export type FormatoReporte = 'excel' | 'pdf'

export interface ReporteDisponible {
  clave:       string
  titulo:      string
  descripcion: string
  /** El grupo en que se ordena el catálogo. */
  area:        string
  /** `excel`, y además `pdf` el que se presenta firmado. */
  formatos:    FormatoReporte[]
  /** Lleva nombres de pacientes con diagnósticos. */
  nominal:     boolean
  /** `false` en una foto de hoy (las existencias): no pide fechas. */
  periodo:     boolean
  filtros:     FiltroReporte[]
  /** Cómo se puede agrupar; la primera es la de por defecto. Vacío si no agrupa. */
  agrupaciones: { value: string; label: string }[]
}

export interface OpcionReporte {
  id:     number
  nombre: string
}

export interface CatalogoReportes {
  alcance:  'administracion' | 'autoridad' | 'medico' | 'odontologo' | 'enfermeria'
  /** Solo las atenciones de quien pregunta. */
  propio:   boolean
  reportes: ReporteDisponible[]
  opciones: { profesionales: OpcionReporte[]; unidades: OpcionReporte[] }
}

export interface FiltrosReporteDispensario {
  desde:                     string
  hasta:                     string
  profesional_id?:           number | null
  especialidad?:             string | null
  tipo_paciente?:            string | null
  unidad_administrativa_id?: number | null
  agrupacion?:               string
}

export type CeldaReporte = string | number | boolean | null

export interface ResultadoReporte {
  columnas:  { clave: string; titulo: string }[]
  filas:     Record<string, CeldaReporte>[]
  total:     number
  /** En pantalla van las primeras 500; el Excel lleva todas. */
  recortado: boolean
}

/** Sin claves vacías: axios mandaría `?profesional_id=` y el backend lo validaría. */
function parametros(filtros: FiltrosReporteDispensario) {
  return Object.fromEntries(
    Object.entries(filtros).filter(([, v]) => v !== null && v !== undefined && v !== ''),
  )
}

export const reportesDispensarioService = {
  catalogo: () =>
    api.get<ApiResponse<CatalogoReportes>>('/dispensario/reportes')
      .then(r => r.data.datos),

  generar: (clave: string, filtros: FiltrosReporteDispensario) =>
    api.get<ApiResponse<ResultadoReporte>>(
      `/dispensario/reportes/${clave}`, { params: parametros(filtros) },
    ).then(r => r.data.datos),

  descargar: (clave: string, filtros: FiltrosReporteDispensario, formato: FormatoReporte) =>
    api.get<Blob>(
      `/dispensario/reportes/${clave}/${formato}`,
      { params: parametros(filtros), responseType: 'blob' },
    ).then(r => r.data),
}
