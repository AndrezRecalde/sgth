import api from '@/lib/axios'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface CertificadoMedico {
  id:                   number
  consulta_medica_id:   number
  folio:                string
  tipo_paciente:        string
  dias_reposo:          number
  fecha_inicio:         string
  fecha_fin:            string
  observaciones?:       string | null
  diagnostico_cie10?: {
    id:          number
    codigo:      string
    descripcion: string
  } | null
  emisor?: {
    nombre_completo?: string
  } | null
  permiso_servidor?: {
    id:    number
    folio: string
  } | null
}

export interface EmitirCertificadoData {
  consulta_medica_id:    number
  dias_reposo:           number
  fecha_inicio:          string
  fecha_fin:             string
  diagnostico_cie10_id?: number | null
  observaciones?:        string | null
}

export const certificadoService = {
  listarPorConsulta: (consultaId: number) =>
    api.get<ApiResponse<PaginatedResponse<CertificadoMedico>>>(
      '/dispensario/certificados-medicos',
      { params: { consulta_medica_id: consultaId, per_page: 50 } }
    ).then(r => r.data.datos?.data ?? []),

  emitir: (data: EmitirCertificadoData) =>
    api.post<ApiResponse<CertificadoMedico>>(
      '/dispensario/certificados-medicos', data
    ).then(r => r.data.datos),

  obtener: (id: number) =>
    api.get<ApiResponse<CertificadoMedico>>(
      `/dispensario/certificados-medicos/${id}`
    ).then(r => r.data.datos),
}
