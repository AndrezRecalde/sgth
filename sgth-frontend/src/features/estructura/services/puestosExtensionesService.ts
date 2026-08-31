import api from '@/lib/axios'
import type { PuestoFormData } from '../schemas/puesto.schema'
import type { ExtensionFormData } from '../schemas/extension.schema'
import type {
  Puesto,
  ExtensionConRelaciones,
  ApiResponse,
  PuestoParams,
  ExtensionTelefonicaParams,
  PuestoConRelaciones,
} from '@/types/api'

export const puestosExtensionesService = {
  // Puestos
  listarPuestos: (params?: PuestoParams) =>
    api.get<{
      exito:   boolean
      mensaje: string
      datos:   PuestoConRelaciones[]
      meta: {
        total:         number
        pagina_actual: number
        por_pagina:    number
        ultima_pagina: number
      }
    }>('/estructura/puestos', { params })
    .then(r => ({
      data:         r.data.datos,
      total:        r.data.meta?.total ?? 0,
      current_page: r.data.meta?.pagina_actual ?? 1,
    })),

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
