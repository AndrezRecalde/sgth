import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface AgendaMedica {
  id:                 number
  folio?:             string | null
  medico_id:          number
  servidor_id?:       number | null
  carga_familiar_id?: number | null
  tipo_atencion:      'medicina_general' | 'odontologia'
  fecha:              string
  hora_inicio?:       string | null
  hora_fin?:          string | null
  registrado_en?:     string | null
  estado:             string
  requiere_triaje?:   boolean
  motivo_solicitud?:  string | null
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
  tipo_atencion:      'medicina_general' | 'odontologia'
  motivo_solicitud?:  string | null
  requiere_triaje:    boolean
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
