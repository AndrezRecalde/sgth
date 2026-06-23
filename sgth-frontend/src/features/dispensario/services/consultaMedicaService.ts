import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface ConsultaMedica {
  id:                     number
  historia_clinica_id:    number
  agenda_medica_id?:      number | null
  medico_id:              number
  fecha_consulta:         string
  hora_consulta:          string
  motivo_consulta:        string
  examen_fisico?:         string | null
  diagnostico_detallado:  string
  diagnostico_cie10?:     number | null
  plan_tratamiento?:      string | null
  notas_medico?:          string | null
}

export interface CrearConsultaData {
  historia_clinica_id:    number
  agenda_medica_id?:      number | null
  fecha_consulta:         string
  hora_consulta:          string
  motivo_consulta:        string
  examen_fisico?:         string | null
  diagnostico_detallado:  string
  diagnostico_cie10?:     number | null
  plan_tratamiento?:      string | null
  notas_medico?:          string | null
}

export const consultaMedicaService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<ConsultaMedica>>>(
      '/dispensario/consultas', { params }
    ).then(r => r.data.datos),

  crear: (data: CrearConsultaData) =>
    api.post<ApiResponse<ConsultaMedica>>(
      '/dispensario/consultas', data
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<ConsultaMedica>>(
      `/dispensario/consultas/${id}`
    ).then(r => r.data.datos),
}
