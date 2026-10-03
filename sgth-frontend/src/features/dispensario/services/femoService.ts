import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { CrearFemoData, FichaSaludOcupacional, FiltrosFemo } from './femo.types'

export type * from './femo.types'

export const femoService = {
  listar: (params?: FiltrosFemo) =>
    api.get<ApiResponse<PaginatedResponse<FichaSaludOcupacional>>>(
      '/dispensario/fichas-sso', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<FichaSaludOcupacional>>(
      `/dispensario/fichas-sso/${id}`
    ).then(r => r.data.datos),

  crear: (data: CrearFemoData) =>
    api.post<ApiResponse<FichaSaludOcupacional>>(
      '/dispensario/fichas-sso', data
    ).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<CrearFemoData>) =>
    api.patch<ApiResponse<FichaSaludOcupacional>>(
      `/dispensario/fichas-sso/${id}`, data
    ).then(r => r.data.datos),

  descargarPdf: (id: number) =>
    api.get(`/dispensario/fichas-sso/${id}/pdf`, {
      responseType: 'blob',
    }).then(r => r.data as Blob),
}
