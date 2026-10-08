import api from '@/lib/axios'
import type {
  ApiResponse,
  CertificadoAprobacion,
  PaginatedResponse,
  PreviaSirha7,
} from '@/types/api'

export interface FiltrosCertificados {
  page?:                     number
  per_page?:                 number
  estado?:                   'pendiente' | 'aprobado' | 'anulado'
  folio?:                    string
  unidad_administrativa_id?: number
  fecha_desde?:              string
  fecha_hasta?:              string
}

/**
 * Los certificados médicos del dispensario, para que TH y Trabajo Social los
 * aprueben y los registren en Sirha7 (`CertificadoAprobacionController`).
 */
export const certificadoAprobacionService = {
  listar: (filtros?: FiltrosCertificados) =>
    api.get<ApiResponse<PaginatedResponse<CertificadoAprobacion>>>('/asistencia/certificados-medicos', { params: filtros })
      .then(r => r.data.datos),

  previa: (id: number) =>
    api.get<ApiResponse<PreviaSirha7>>(`/asistencia/certificados-medicos/${id}/sirha7`)
      .then(r => r.data.datos),

  aprobar: (id: number, leaveId: number) =>
    api.post<ApiResponse<CertificadoAprobacion>>(
      `/asistencia/certificados-medicos/${id}/aprobar-sirha7`, { leave_id: leaveId }
    ).then(r => r.data.datos),

  aprobarSinSirha7: (id: number, nota: string) =>
    api.post<ApiResponse<CertificadoAprobacion>>(
      `/asistencia/certificados-medicos/${id}/aprobar-sin-sirha7`, { nota }
    ).then(r => r.data.datos),
}
