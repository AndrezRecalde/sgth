import api from '@/lib/axios'
import type { ApiResponse, ContratoServidor, ContratoParams } from '@/types/api'
import type { ContratoFormData } from '../schemas/contrato.schema'

export const contratoService = {
  listar: (servidorId: number, params?: ContratoParams) =>
    api.get<ApiResponse<ContratoServidor[]>>(
      `/expediente/servidores/${servidorId}/contratos`, { params }
    ).then(r => r.data.datos ?? []),

  crear: (servidorId: number, data: ContratoFormData) =>
    api.post<ApiResponse<ContratoServidor>>(
      `/expediente/servidores/${servidorId}/contratos`, data
    ).then(r => r.data.datos),

  editar: (servidorId: number, id: number, data: ContratoFormData) =>
    api.put<ApiResponse<ContratoServidor>>(
      `/expediente/servidores/${servidorId}/contratos/${id}`, data
    ).then(r => r.data.datos),

  eliminar: (servidorId: number, id: number) =>
    api.delete<ApiResponse<void>>(
      `/expediente/servidores/${servidorId}/contratos/${id}`
    ).then(r => r.data),
}
