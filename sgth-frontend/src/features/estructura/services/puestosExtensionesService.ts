import api from '@/lib/axios'
import type { PuestoFormData } from '../schemas/puesto.schema'
import type { ExtensionFormData } from '../schemas/extension.schema'
import type {
  Puesto,
  ExtensionTelefonica,
  ExtensionConRelaciones,
  ApiResponse,
  PaginatedResponse,
  PuestoParams,
  ExtensionTelefonicaParams,
  PuestoConRelaciones,
} from '@/types/api'

export const puestosExtensionesService = {
  // Puestos
  listarPuestos: (params?: PuestoParams) =>
    api.get<ApiResponse<PaginatedResponse<PuestoConRelaciones>>>('/estructura/puestos', { params })
    .then(r => {
      const data = r.data.datos;
      return {
        data: data.data,
        total: data.total ?? 0,
        current_page: data.current_page ?? 1,
      };
    }),

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
    api.get<ApiResponse<ExtensionConRelaciones[]>>(
      '/estructura/directorio-telefonico', { params }
    ).then(r => r.data.datos),

  crearExtension: (data: ExtensionFormData) =>
    api.post<ApiResponse<ExtensionConRelaciones>>(
      '/estructura/extensiones', data
    ).then(r => r.data.datos),

  editarExtension: (id: number, data: ExtensionFormData) =>
    api.put<ApiResponse<ExtensionConRelaciones>>(
      `/estructura/extensiones/${id}`, data
    ).then(r => r.data.datos),

  eliminarExtension: (id: number) =>
    api.delete<ApiResponse<void>>(
      `/estructura/extensiones/${id}`
    ).then(r => r.data),
}
