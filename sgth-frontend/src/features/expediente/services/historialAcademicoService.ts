import api from '@/lib/axios'
import type { ApiResponse, HistorialAcademicoServidor } from '@/types/api'
import type { HistorialAcademicoFormData } from '../schemas/historialAcademico.schema'

export const historialAcademicoService = {
  listar: (servidorId: number) =>
    api
      .get<ApiResponse<HistorialAcademicoServidor[]>>(
        `/expediente/servidores/${servidorId}/historial-academico`,
      )
      .then((r) => r.data.datos ?? []),

  crear: (servidorId: number, data: FormData | HistorialAcademicoFormData) =>
    api
      .post<ApiResponse<HistorialAcademicoServidor>>(
        `/expediente/servidores/${servidorId}/historial-academico`, data,
      )
      .then((r) => r.data.datos),

  editar: (
    servidorId: number,
    id: number,
    data: FormData | HistorialAcademicoFormData,
  ) =>
    api
      .put<ApiResponse<HistorialAcademicoServidor>>(
        `/expediente/servidores/${servidorId}/historial-academico/${id}`, data,
      )
      .then((r) => r.data.datos),

  eliminar: (servidorId: number, id: number) =>
    api
      .delete<ApiResponse<void>>(
        `/expediente/servidores/${servidorId}/historial-academico/${id}`,
      )
      .then((r) => r.data),
}
