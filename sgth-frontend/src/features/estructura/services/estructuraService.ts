import api from '@/lib/axios'
import type { UnidadFormData } from '../schemas/unidad.schema'
import type { PuestoFormData } from '../schemas/puesto.schema'
import type { ExtensionFormData } from '../schemas/extension.schema'
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
    api.get<ApiResponse<UnidadAdministrativa[]>>(
      '/estructura/organigrama'
    ).then(r => r.data.datos),

  // Puestos
  listarPuestos: (params?: PuestoParams) =>
    api.get<ApiResponse<PaginatedResponse<Puesto>>>(
      '/estructura/puestos', { params }
    ).then(r => r.data.datos),

  crearPuesto: (data: PuestoFormData) =>
    api.post<ApiResponse<Puesto>>(
      '/estructura/puestos', data
    ).then(r => r.data.datos),

  editarPuesto: (id: number, data: PuestoFormData) =>
    api.put<ApiResponse<Puesto>>(
      `/estructura/puestos/${id}`, data
    ).then(r => r.data.datos),

  eliminarPuesto: (id: number) =>
    api.delete<ApiResponse<void>>(
      `/estructura/puestos/${id}`
    ).then(r => r.data),

  // Directorio telefónico
  directorio: (params?: ExtensionTelefonicaParams) =>
    api.get<ApiResponse<ExtensionTelefonica[]>>(
      '/estructura/directorio-telefonico', { params }
    ).then(r => r.data.datos),

  crearExtension: (data: ExtensionFormData) =>
    api.post<ApiResponse<ExtensionTelefonica>>(
      '/estructura/extensiones', data
    ).then(r => r.data.datos),

  editarExtension: (id: number, data: ExtensionFormData) =>
    api.put<ApiResponse<ExtensionTelefonica>>(
      `/estructura/extensiones/${id}`, data
    ).then(r => r.data.datos),

  eliminarExtension: (id: number) =>
    api.delete<ApiResponse<void>>(
      `/estructura/extensiones/${id}`
    ).then(r => r.data),

  // Catálogos
  tiposUnidad: () =>
    api.get<ApiResponse<TipoUnidad[]>>(
      '/catalogos/tipos-unidad'
    ).then(r => r.data.datos),
}
