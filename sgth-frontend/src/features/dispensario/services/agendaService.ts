import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export type EstadoAgenda =
  | 'en_espera'
  | 'en_sala'
  | 'en_consulta'
  | 'atendido'
  | 'no_presentado'
  | 'cancelado'

export interface AgendaMedica {
  id:                           number
  folio?:                       string | null
  medico_id:                    number
  servidor_id?:                 number | null
  carga_familiar_id?:           number | null
  tipo_atencion:                'medicina_general' | 'odontologia'
  fecha:                        string
  hora_inicio?:                 string | null
  hora_fin?:                    string | null
  registrado_en?:               string | null
  estado:                       EstadoAgenda
  requiere_triaje?:             boolean
  motivo_solicitud?:            string | null
  historia_clinica_id?:         number | null
  marcado_no_presentado_en?:    string | null
  reactivado_en?:               string | null
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
  consulta_medica?: {
    id: number
    tipo_atencion?: string
    tipo_diagnostico?: string
    diagnostico_detallado?: string
  } | null
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

  listosParaConsulta: () =>
    api.get<ApiResponse<AgendaMedica[]>>(
      '/dispensario/agenda/listos-para-consulta'
    ).then(r => r.data.datos),

  turnosDelDia: () =>
    api.get<ApiResponse<AgendaMedica[]>>(
      '/dispensario/agenda/turnos-del-dia'
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
}
