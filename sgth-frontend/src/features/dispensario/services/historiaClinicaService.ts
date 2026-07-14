import api from '@/lib/axios'
import type {
  ApiResponse, PaginatedResponse,
} from '@/types/api'
import type {
  Alergia, Antecedente,
} from './contextoConsultaService'

export interface CrearAlergiaData {
  tipo:        string
  descripcion: string
  severidad:   string
  observacion?: string | null
}

export interface CrearAntecedenteData {
  tipo:              string
  descripcion:       string
  fecha_aproximada?: number | null
}

export interface HistoriaClinica {
  id:                  number
  numero_historia?:    string | null
  cedula_paciente?:    string | null
  tipo_paciente?:      'servidor' | 'familiar' | 'candidato'
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
  agregarAlergia: (
    historiaId: number,
    data: CrearAlergiaData
  ) =>
    api.post<ApiResponse<Alergia>>(
      `/dispensario/historias-clinicas/${historiaId}/alergias`,
      data
    ).then(r => r.data.datos),

  anularAlergia: (
    historiaId: number,
    alergiaId: number,
    motivo: string
  ) =>
    api.patch<ApiResponse<unknown>>(
      `/dispensario/historias-clinicas/${historiaId}/alergias/${alergiaId}/anular`,
      { motivo_anulacion: motivo }
    ).then(r => r.data.datos),

  agregarAntecedente: (
    historiaId: number,
    data: CrearAntecedenteData
  ) =>
    api.post<ApiResponse<Antecedente>>(
      `/dispensario/historias-clinicas/${historiaId}/antecedentes`,
      data
    ).then(r => r.data.datos),

  anularAntecedente: (
    historiaId: number,
    antecedenteId: number,
    motivo: string
  ) =>
    api.patch<ApiResponse<unknown>>(
      `/dispensario/historias-clinicas/${historiaId}/antecedentes/${antecedenteId}/anular`,
      { motivo_anulacion: motivo }
    ).then(r => r.data.datos),

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

  buscarPorCedula: (cedula: string) =>
    api.get<ApiResponse<HistoriaClinica | null>>(
      '/dispensario/historias-clinicas/buscar-por-cedula',
      { params: { cedula } }
    ).then(r => r.data.datos),

  crearPorCedula: (data: {
    cedula_paciente: string
    tipo_paciente?:  'servidor' | 'familiar' | 'candidato'
  }) =>
    api.post<ApiResponse<HistoriaClinica>>(
      '/dispensario/historias-clinicas/crear-por-cedula',
      data
    ).then(r => r.data.datos),
}
