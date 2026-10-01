import api from '@/lib/axios'
import type {
  ApiResponse,
  EstadoSumario,
  EstadoVistoBueno,
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

/** Cuerpo de `POST sumarios/{id}/resolver`: la falta probada y su sanción. */
export type ResolverSumarioData = {
  tipo_falta: TipoFalta
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
}
