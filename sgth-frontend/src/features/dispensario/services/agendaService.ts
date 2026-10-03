import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { AgendaMedica, CrearAgendaData } from './agenda.types'

export type { EstadoAgenda, AgendaMedica, CrearAgendaData } from './agenda.types'

export const agendaService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<AgendaMedica>>>(
      '/dispensario/agenda', { params }
    ).then(r => r.data.datos),

  crear: (data: CrearAgendaData) =>
    api.post<ApiResponse<AgendaMedica>>(
      '/dispensario/agenda', data
    ).then(r => r.data.datos),

  cancelar: (id: number) =>
    api.delete<ApiResponse<AgendaMedica>>(
      `/dispensario/agenda/${id}`
    ).then(r => r.data.datos),

  turnosDelDia: (params?: {
    fecha_desde?: string
    fecha_hasta?: string
  }) =>
    api.get<ApiResponse<AgendaMedica[]>>(
      '/dispensario/agenda/turnos-del-dia',
      { params }
    ).then(r => r.data.datos),

  marcarNoPresentado: (id: number) =>
    api.patch<ApiResponse<AgendaMedica>>(
      `/dispensario/agenda/${id}/no-presentado`
    ).then(r => r.data.datos),

  reactivar: (id: number) =>
    api.patch<ApiResponse<AgendaMedica>>(
      `/dispensario/agenda/${id}/reactivar`
    ).then(r => r.data.datos),

  marcarEnConsulta: (id: number) =>
    api.patch<ApiResponse<AgendaMedica>>(
      `/dispensario/agenda/${id}/en-consulta`
    ).then(r => r.data.datos),

  obtenerPorFolio: (folio: string) =>
    api.get<ApiResponse<AgendaMedica>>(
      `/dispensario/agenda/por-folio/${folio}`
    ).then(r => r.data.datos),
}
