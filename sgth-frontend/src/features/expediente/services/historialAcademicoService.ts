import api from '@/lib/axios'
import type { ApiResponse, FormacionPorValidar, HistorialAcademicoServidor } from '@/types/api'
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

  // ── Lo que registra el servidor desde el portal (2026-10-17) ─────────

  /** Los títulos y cursos de toda la institución que esperan a Talento Humano. */
  porValidar: () =>
    api
      .get<ApiResponse<FormacionPorValidar[]>>('/expediente/formacion-por-validar')
      .then((r) => r.data.datos ?? []),

  validar: (servidorId: number, id: number) =>
    api
      .post<ApiResponse<HistorialAcademicoServidor>>(
        `/expediente/servidores/${servidorId}/historial-academico/${id}/validar`,
      )
      .then((r) => r.data),

  rechazar: (servidorId: number, id: number, motivo: string) =>
    api
      .post<ApiResponse<HistorialAcademicoServidor>>(
        `/expediente/servidores/${servidorId}/historial-academico/${id}/rechazar`, { motivo },
      )
      .then((r) => r.data),

  documento: (servidorId: number, id: number) =>
    api
      .get<Blob>(
        `/expediente/servidores/${servidorId}/historial-academico/${id}/documento`,
        { responseType: 'blob' },
      )
      .then((r) => r.data),
}
