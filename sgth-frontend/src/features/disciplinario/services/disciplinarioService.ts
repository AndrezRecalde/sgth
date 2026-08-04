import api from '@/lib/axios'
import type {
  ApiResponse,
  EstadoSumario,
  EstadoVistoBueno,
  Sumario,
  SumarioFormData,
  VistoBueno,
  VistoBuenoFormData,
} from '@/types/api'

type Paginado<T> = { data: T[]; total?: number }

export type SumarioParams = {
  estado?: EstadoSumario
  servidor_id?: number
  anio?: number
  per_page?: number
}

export type VistoBuenoParams = {
  estado?: EstadoVistoBueno
  servidor_id?: number
  anio?: number
  per_page?: number
}

export type AvanzarSumarioData = {
  estado: EstadoSumario
  fecha_notificacion?: string | null
  fecha_termino_prueba?: string | null
  fecha_informe?: string | null
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
    api.get<ApiResponse<Paginado<Sumario>>>(
      `${BASE}/sumarios`, { params }
    ).then(r => r.data.datos),

  crearSumario: (data: SumarioFormData) =>
    api.post<ApiResponse<Sumario>>(`${BASE}/sumarios`, data).then(r => r.data.datos),

  avanzarSumario: (id: number, data: AvanzarSumarioData) =>
    api.put<ApiResponse<Sumario>>(`${BASE}/sumarios/${id}/avanzar`, data).then(r => r.data.datos),

  listarVistosBuenos: (params?: VistoBuenoParams) =>
    api.get<ApiResponse<Paginado<VistoBueno>>>(
      `${BASE}/vistos-buenos`, { params }
    ).then(r => r.data.datos),

  crearVistoBueno: (data: VistoBuenoFormData) =>
    api.post<ApiResponse<VistoBueno>>(`${BASE}/vistos-buenos`, data).then(r => r.data.datos),

  transicionarVistoBueno: (id: number, data: TransicionarVistoBuenoData) =>
    api.put<ApiResponse<VistoBueno>>(
      `${BASE}/vistos-buenos/${id}/transicionar`, data
    ).then(r => r.data.datos),
}
