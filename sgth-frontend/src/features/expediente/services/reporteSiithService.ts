import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type PortalReporte = 'siith' | 'sut'
export type SituacionReporte = 'pendientes' | 'transcritos' | 'todos'

/** Un acto para transcribir al portal del Ministerio (fase 7.5). */
export type ActoDelReporte = {
  movimiento_id: number
  numero: string | null
  estado: string
  fecha_registro: string | null
  fecha_efectiva: string | null
  clase: string | null
  causal: string | null
  cedula: string | null
  servidor: string
  modalidad: string | null
  origen: { puesto: string | null; grado: string | null; unidad: string | null; remuneracion: number | null }
  destino: { puesto: string | null; grado: string | null; unidad: string | null; remuneracion: number | null }
  base_legal: string | null
  documento: string | null
  transcrito: boolean
  transcrito_en: string | null
  /** Se anuló después de transcribirse: falta anularlo en el portal. */
  anulacion_pendiente: boolean
}

export type ReporteSiith = {
  portal: PortalReporte
  periodo: string
  etiqueta: string
  situacion: SituacionReporte
  actos: ActoDelReporte[]
  resumen: { total: number; pendientes: number; anulaciones_pendientes: number }
}

export type ClaseDelReporte = {
  clase: string
  etiqueta: string
  familia: string
  reportable_siith: boolean
  reportable_sut: boolean
  actualizado_por: string | null
  actualizado_en: string | null
}

type Filtros = { portal: PortalReporte; periodo: string; situacion: SituacionReporte }

export const reporteSiithService = {
  obtener: (f: Filtros) =>
    api.get<ApiResponse<ReporteSiith>>('/reportes/siith-sut', { params: f }).then((r) => r.data.datos),

  excel: (f: Filtros) =>
    api.get('/reportes/siith-sut/excel', { params: f, responseType: 'blob' }).then((r) => r.data as Blob),

  transcribir: (portal: PortalReporte, movimientoIds: number[]) =>
    api
      .post<ApiResponse<{ transcritos: number }>>('/reportes/siith-sut/transcribir', { portal, movimiento_ids: movimientoIds })
      .then((r) => r.data),

  configuracion: () =>
    api.get<ApiResponse<ClaseDelReporte[]>>('/reportes/siith-sut/configuracion').then((r) => r.data.datos),

  actualizarConfiguracion: (clase: string, marcas: Partial<Pick<ClaseDelReporte, 'reportable_siith' | 'reportable_sut'>>) =>
    api.patch<ApiResponse<ClaseDelReporte[]>>(`/reportes/siith-sut/configuracion/${clase}`, marcas).then((r) => r.data),
}
