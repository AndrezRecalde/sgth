import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface AgendaMedica {
  id:                 number
  medico_id:          number
  servidor_id?:       number | null
  carga_familiar_id?: number | null
  fecha:              string
  hora_inicio:        string
  hora_fin:           string
  estado:             string
  motivo_solicitud:   string
  medico?: {
    id: number
    nombre_completo?: string
    usuario_ti?: string
  }
  servidor?: {
    id: number
    nombre: string
    apellido: string
  } | null
  carga_familiar?: {
    id: number
    nombres: string
    apellidos: string
  } | null
  triaje?: unknown | null
}

export interface CrearAgendaData {
  medico_id:          number
  servidor_id?:       number | null
  carga_familiar_id?: number | null
  fecha:              string
  hora_inicio:        string
  hora_fin:           string
  motivo_solicitud:   string
}

export const agendaService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<AgendaMedica>>>(
      '/dispensario/agenda', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<AgendaMedica>>(
      `/dispensario/agenda/${id}`
    ).then(r => r.data.datos),

  crear: (data: CrearAgendaData) =>
    api.post<ApiResponse<AgendaMedica>>(
      '/dispensario/agenda', data
    ).then(r => r.data.datos),

  cancelar: (id: number) =>
    api.delete<ApiResponse<AgendaMedica>>(
      `/dispensario/agenda/${id}`
    ).then(r => r.data.datos),
}
