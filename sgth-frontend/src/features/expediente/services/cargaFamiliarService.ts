import api from '@/lib/axios'
import type {
  ApiResponse,
  CargaFamiliar,
} from '@/types/api'
import type { CargaFamiliarFormData } from '../schemas/cargaFamiliar.schema'

export const cargaFamiliarService = {
  listar: (servidorId: number) =>
    api
      .get<ApiResponse<CargaFamiliar[]>>(
        `/expediente/servidores/${servidorId}/cargas-familiares`,
      )
      .then((r) => r.data.datos ?? []),

  crear: (servidorId: number, data: CargaFamiliarFormData) =>
    api
      .post<ApiResponse<CargaFamiliar>>(
        `/expediente/servidores/${servidorId}/cargas-familiares`, data,
      )
      .then((r) => r.data.datos),

  editar: (servidorId: number, id: number, data: CargaFamiliarFormData) =>
    api
      .put<ApiResponse<CargaFamiliar>>(
        `/expediente/servidores/${servidorId}/cargas-familiares/${id}`, data,
      )
      .then((r) => r.data.datos),

  eliminar: (servidorId: number, id: number) =>
    api
      .delete<ApiResponse<void>>(
        `/expediente/servidores/${servidorId}/cargas-familiares/${id}`,
      )
      .then((r) => r.data),

  toggleEstado: (servidorId: number, id: number) =>
    api
      .post<ApiResponse<CargaFamiliar>>(
        `/expediente/servidores/${servidorId}/cargas-familiares/${id}/toggle-estado`,
      )
      .then((r) => r.data.datos),
}
