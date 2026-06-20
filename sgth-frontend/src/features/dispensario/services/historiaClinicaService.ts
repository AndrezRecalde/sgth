import api from '@/lib/axios'
import type {
  ApiResponse, PaginatedResponse,
} from '@/types/api'

export interface HistoriaClinica {
  id:                  number
  servidor_id?:        number | null
  beneficiario_id?:    number | null
  grupo_sanguineo?:    string | null
  medicacion_habitual?: string | null
  estado:              boolean
  servidor?: {
    id: number
    nombre: string
    apellido: string
    cedula: string
  } | null
  beneficiario?: {
    id: number
    nombre: string
    apellido: string
    cedula: string
    tipo_familiar: string
  } | null
  alergias?:      AlergiaPaciente[]
  antecedentes?:  AntecedentePaciente[]
  consultas_medicas?: unknown[]
}

export interface AlergiaPaciente {
  id:           number
  tipo:         string
  descripcion:  string
  severidad?:   string | null
  observacion?: string | null
}

export interface AntecedentePaciente {
  id:                number
  tipo:              string
  descripcion:       string
  fecha_aproximada?: number | null
}

export const historiaClinicaService = {
  listar: (params?: Record<string, unknown>) =>
    api.get<ApiResponse<PaginatedResponse<HistoriaClinica>>>(
      '/dispensario/historias-clinicas', { params }
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<HistoriaClinica>>(
      `/dispensario/historias-clinicas/${id}`
    ).then(r => r.data.datos),

  crear: (data: {
    servidor_id?:     number | null
    beneficiario_id?: number | null
    grupo_sanguineo?: string | null
  }) =>
    api.post<ApiResponse<HistoriaClinica>>(
      '/dispensario/historias-clinicas', data
    ).then(r => r.data.datos),
}
