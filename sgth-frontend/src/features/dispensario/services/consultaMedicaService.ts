import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { DiagnosticoCie10 } from './cie10Service'

export interface DiagnosticoSecundario {
  id:                  number
  diagnostico_cie10_id: number
  diagnostico?:        DiagnosticoCie10
}

export interface ConsultaMedica {
  id:                       number
  historia_clinica_id:      number
  agenda_medica_id?:        number | null
  medico_id:                number
  fecha_consulta:           string
  hora_consulta:            string
  tipo_atencion:            string
  tipo_diagnostico:         string
  motivo_consulta:          string
  enfermedad_actual?:       string | null
  examen_fisico?:           string | null
  diagnostico_detallado:    string
  diagnostico_cie10?:       number | null
  diagnostico_cie10_id?:    number | null
  diagnosticos_secundarios?: DiagnosticoSecundario[]
  plan_tratamiento?:        string | null
  notas_medico?:            string | null
  medico?: {
    id: number
    nombre_completo?: string
  }
}

export interface CrearConsultaData {
  historia_clinica_id:       number
  agenda_medica_id?:         number | null
  fecha_consulta:            string
  hora_consulta:             string
  tipo_atencion:             string
  tipo_diagnostico:          string
  motivo_consulta:           string
  enfermedad_actual?:        string | null
  examen_fisico?:            string | null
  diagnostico_detallado:     string
  diagnostico_cie10_id?:     number | null
  diagnosticos_secundarios?: number[]
  plan_tratamiento?:         string | null
  notas_medico?:             string | null
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

  listarPorHistoria: (historiaClinicaId: number) =>
    api.get<ApiResponse<PaginatedResponse<ConsultaMedica>>>(
      '/dispensario/consultas',
      { params: {
        historia_clinica_id: historiaClinicaId,
        per_page: 50
      }}
    ).then(r => r.data.datos?.data ?? []),
}
