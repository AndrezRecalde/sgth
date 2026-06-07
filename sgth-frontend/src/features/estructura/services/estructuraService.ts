import api from '@/lib/axios'
import type { UnidadFormData } from '../schemas/unidad.schema'
import type {
  UnidadAdministrativa,
  TipoUnidad,
  ApiResponse,
  UnidadAdministrativaParams,
  UnidadConRelaciones,
} from '@/types/api'

export const estructuraService = {
  // Unidades administrativas
  listarUnidades: (params?: UnidadAdministrativaParams) =>
    api.get<ApiResponse<UnidadAdministrativa[]>>(
      '/estructura/unidades-administrativas', { params }
    ).then(r => r.data.datos),

  listarTodasUnidades: (params?: { nivel?: number; estado?: boolean }) =>
    api.get<ApiResponse<UnidadAdministrativa[]>>(
      '/estructura/unidades-administrativas/todas',
      { params }
    ).then(r => r.data.datos ?? []),

  obtenerUnidad: (id: number) =>
    api.get<ApiResponse<UnidadAdministrativa>>(
      `/estructura/unidades-administrativas/${id}`
    ).then(r => r.data.datos),

  crearUnidad: (data: UnidadFormData) =>
    api.post<ApiResponse<UnidadAdministrativa>>(
      '/estructura/unidades-administrativas', data
    ).then(r => r.data.datos),

  editarUnidad: (id: number, data: UnidadFormData) =>
    api.put<ApiResponse<UnidadAdministrativa>>(
      `/estructura/unidades-administrativas/${id}`, data
    ).then(r => r.data.datos),

  eliminarUnidad: (id: number) =>
    api.delete<ApiResponse<void>>(
      `/estructura/unidades-administrativas/${id}`
    ).then(r => r.data),

  // Organigrama (árbol completo)
  organigrama: () =>
    api.get<ApiResponse<UnidadConRelaciones[]>>(
      '/estructura/organigrama'
    ).then(r => r.data.datos),

  // Catálogos
  tiposUnidad: () =>
    api.get<ApiResponse<TipoUnidad[]>>(
      '/catalogos/tipos-unidad'
    ).then(r => r.data.datos),
}
