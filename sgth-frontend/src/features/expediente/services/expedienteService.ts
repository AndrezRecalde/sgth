import api from '@/lib/axios'
import type {
  ApiResponse,
  PaginatedResponse,
  Servidor,
  ServidorParams,
} from '@/types/api'
import type { ServidorFormData } from '../schemas/servidor.schema'

export const expedienteService = {
  // Servidores
  listar: (params?: ServidorParams) =>
    api.get<ApiResponse<PaginatedResponse<Servidor>>>(
      '/expediente/servidores', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<Servidor>>(
      `/expediente/servidores/${id}`
    ).then(r => r.data.datos),

  crear: (data: ServidorFormData) =>
    api.post<ApiResponse<Servidor>>(
      '/expediente/servidores', data
    ).then(r => r.data.datos),

  editar: (id: number, data: ServidorFormData) =>
    api.put<ApiResponse<Servidor>>(
      `/expediente/servidores/${id}`, data
    ).then(r => r.data.datos),
}
