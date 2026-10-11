import api from '@/lib/axios'
import type {
  ApiResponse,
  EstadoSumario,
  EstadoVistoBueno,
  FaltaReglamentoInterno,
  PaginatedResponse,
  Sumario,
  SumarioFormData,
  TipoFalta,
  TipoSancion,
  VistoBueno,
  VistoBuenoFormData,
} from '@/types/api'

export type SumarioParams = {
  estado?: EstadoSumario
  servidor_id?: number
  anio?: number
  page?: number
  per_page?: number
}

export type VistoBuenoParams = {
  estado?: EstadoVistoBueno
  servidor_id?: number
  anio?: number
  page?: number
  per_page?: number
}

export type AvanzarSumarioData = {
  estado: EstadoSumario
  fecha_notificacion?: string | null
  fecha_termino_prueba?: string | null
  fecha_informe?: string | null
}

/**
 * Cuerpo de `POST sumarios/{id}/resolver`: la falta probada y su sanción. En el
 * sumario de la LOSEP, su gravedad; en el del reglamento interno de un obrero,
 * su falta del catálogo (fase 4.4).
 */
export type ResolverSumarioData = {
  tipo_falta?: TipoFalta | null
  falta_reglamento_id?: number | null
  tipo_sancion: TipoSancion
  porcentaje_multa?: number | null
  dias_suspension?: number | null
  fecha_efectiva?: string | null
  observaciones?: string | null
}

export type TransicionarVistoBuenoData = {
  estado: EstadoVistoBueno
  fecha_notificacion?: string | null
  fecha_resolucion?: string | null
  resolucion_detalle?: string | null
  numero_tramite_mdt?: string | null
  inspectoria?: string | null
  inspector_nombre?: string | null
  impugnacion_referencia?: string | null
  fecha_impugnacion?: string | null
}

/** Una falta del catálogo del reglamento interno, al crearla o corregirla (fase 4.4). */
export type FaltaReglamentoData = {
  articulo: string
  descripcion: string
  gravedad?: string | null
  admite_amonestacion_verbal: boolean
  admite_amonestacion_escrita: boolean
  admite_multa: boolean
  porcentaje_maximo_multa?: number | null
  activo: boolean
}

const BASE = '/disciplinario'

export const disciplinarioService = {
  listarSumarios: (params?: SumarioParams) =>
    api.get<ApiResponse<PaginatedResponse<Sumario>>>(
      `${BASE}/sumarios`, { params }
    ).then(r => r.data.datos),

  crearSumario: (data: SumarioFormData) =>
    api.post<ApiResponse<Sumario>>(`${BASE}/sumarios`, data).then(r => r.data.datos),

  avanzarSumario: (id: number, data: AvanzarSumarioData) =>
    api.put<ApiResponse<Sumario>>(`${BASE}/sumarios/${id}/avanzar`, data).then(r => r.data.datos),

  resolverSumario: (id: number, data: ResolverSumarioData) =>
    api.post<ApiResponse<Sumario>>(`${BASE}/sumarios/${id}/resolver`, data).then(r => r.data.datos),

  listarFaltasReglamento: (params?: { activas?: boolean }) =>
    api.get<ApiResponse<FaltaReglamentoInterno[]>>(
      `${BASE}/reglamento-interno/faltas`, { params }
    ).then(r => r.data.datos),

  crearFaltaReglamento: (data: FaltaReglamentoData) =>
    api.post<ApiResponse<FaltaReglamentoInterno>>(`${BASE}/reglamento-interno/faltas`, data)
      .then(r => r.data.datos),

  actualizarFaltaReglamento: (id: number, data: FaltaReglamentoData) =>
    api.put<ApiResponse<FaltaReglamentoInterno>>(`${BASE}/reglamento-interno/faltas/${id}`, data)
      .then(r => r.data.datos),

  listarVistosBuenos: (params?: VistoBuenoParams) =>
    api.get<ApiResponse<PaginatedResponse<VistoBueno>>>(
      `${BASE}/vistos-buenos`, { params }
    ).then(r => r.data.datos),

  crearVistoBueno: (data: VistoBuenoFormData) =>
    api.post<ApiResponse<VistoBueno>>(`${BASE}/vistos-buenos`, data).then(r => r.data.datos),

  transicionarVistoBueno: (id: number, data: TransicionarVistoBuenoData) =>
    api.put<ApiResponse<VistoBueno>>(
      `${BASE}/vistos-buenos/${id}/transicionar`, data
    ).then(r => r.data.datos),

  /** La resolución del Inspector en PDF; reemplaza la anterior (2026-10-04). */
  adjuntarResolucion: (id: number, archivo: File) => {
    const datos = new FormData()
    datos.append('archivo', archivo)
    // La instancia de axios fija JSON: sin esto el PDF llegaba como texto y el
    // backend respondía «El campo archivo debe ser un archivo».
    return api.post<ApiResponse<VistoBueno>>(`${BASE}/vistos-buenos/${id}/documento`, datos, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data.datos)
  },

  descargarResolucion: (id: number) =>
    api.get(`${BASE}/vistos-buenos/${id}/documento`, { responseType: 'blob' })
      .then(r => r.data as Blob),
}
