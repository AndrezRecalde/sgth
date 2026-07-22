import api from '@/lib/axios'
import type { ApiResponse, Subrogacion, SubrogacionParams } from '@/types/api'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'

export const subrogacionService = {
  listarActivas: (params?: SubrogacionParams) =>
    api
      .get<ApiResponse<Subrogacion[]>>('/expediente/subrogaciones/activas', { params })
      .then((r) => r.data.datos ?? []),

  listarPorServidor: (servidorId: number) =>
    api
      .get<ApiResponse<Subrogacion[]>>(`/expediente/subrogaciones/servidor/${servidorId}`)
      .then((r) => r.data.datos ?? []),

  registrar: (data: SubrogacionFormData) =>
    api
      .post<ApiResponse<Subrogacion>>('/expediente/subrogaciones', data)
      .then((r) => r.data.datos),

  finalizar: (id: number) =>
    api
      .put<ApiResponse<Subrogacion>>(`/expediente/subrogaciones/${id}/finalizar`)
      .then((r) => r.data.datos),

  cancelar: (id: number, motivo: string) =>
    api
      .put<ApiResponse<Subrogacion>>(`/expediente/subrogaciones/${id}/cancelar`, { motivo })
      .then((r) => r.data.datos),
}
