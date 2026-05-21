import api from '@/lib/axios'
import type {
  UnidadAdministrativa,
  Puesto,
  TipoUnidad,
  ExtensionTelefonica,
  ApiResponse,
  PaginatedResponse,
  UnidadAdministrativaParams,
  PuestoParams,
  ExtensionTelefonicaParams,
} from '@/types/api'

export const estructuraService = {
  // Unidades administrativas
  listarUnidades: (params?: UnidadAdministrativaParams) =>
    api.get<ApiResponse<UnidadAdministrativa[]>>(
      '/estructura/unidades-administrativas', { params }
    ).then(r => r.data.datos),

  obtenerUnidad: (id: number) =>
    api.get<ApiResponse<UnidadAdministrativa>>(
      `/estructura/unidades-administrativas/${id}`
    ).then(r => r.data.datos),

  // Organigrama (árbol completo)
  organigrama: () =>
    api.get<ApiResponse<UnidadAdministrativa[]>>(
      '/estructura/organigrama'
    ).then(r => r.data.datos),

  // Puestos
  listarPuestos: (params?: PuestoParams) =>
    api.get<ApiResponse<PaginatedResponse<Puesto>>>(
      '/estructura/puestos', { params }
    ).then(r => r.data.datos),

  // Directorio telefónico
  directorio: (params?: ExtensionTelefonicaParams) =>
    api.get<ApiResponse<ExtensionTelefonica[]>>(
      '/estructura/directorio-telefonico', { params }
    ).then(r => r.data.datos),

  // Catálogos
  tiposUnidad: () =>
    api.get<ApiResponse<TipoUnidad[]>>(
      '/catalogos/tipos-unidad'
    ).then(r => r.data.datos),
}
