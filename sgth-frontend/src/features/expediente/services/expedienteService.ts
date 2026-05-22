import api from '@/lib/axios'
import type {
  ApiResponse,
  PaginatedResponse,
  Servidor,
  ServidorParams,
} from '@/types/api'
import type { ServidorFormData } from '../schemas/servidor.schema'

export const expedienteService = {
  listar: (params?: ServidorParams) =>
    api.get<ApiResponse<PaginatedResponse<Servidor>>>(
      '/expediente/servidores', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<Servidor>>(
      `/expediente/servidores/${id}`
    ).then(r => r.data.datos),

  // Registro básico — solo datos personales obligatorios
  crearBasico: (data: Partial<ServidorFormData>) =>
    api.post<ApiResponse<Servidor>>(
      '/expediente/servidores/basico', data
    ).then(r => r.data.datos),

  // Registro completo — todos los datos
  crear: (data: ServidorFormData) =>
    api.post<ApiResponse<Servidor>>(
      '/expediente/servidores', data
    ).then(r => r.data.datos),

  editar: (id: number, data: Partial<ServidorFormData>) =>
    api.put<ApiResponse<Servidor>>(
      `/expediente/servidores/${id}`, data
    ).then(r => r.data.datos),
}
