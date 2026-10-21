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
  /**
   * Del servidor titular: TH o Trabajo Social lo aprueban y lo registran en
   * Sirha7 (desde el 2026-10-08 ya no se crea un permiso). Nulo en familiares.
   */
  servidor_id?:         number | null
  aprobado_en?:         string | null
  /** `sgth`: lo escribió el SGTH en Sirha7; `manual`: TH lo cargó a mano. */
  registro_sirha7?:     'sgth' | 'manual' | null
  sirha7_leave_nombre?: string | null
  /** Con fecha, el certificado está anulado: la fila se conserva, marcada. */
  anulado_en?:       string | null
  motivo_anulacion?: string | null
  anulador?: {
    nombre_completo?: string
    usuario_ti?:      string
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

  /**
   * Anula el certificado y, si ya se registró en Sirha7, lo retira de allí:
   * los días de reposo no pueden quedar justificando una ausencia. Si Sirha7
   * no responde, no se anula. Desde el 2026-10-08 el certificado no crea un
   * permiso de asistencia, así que no hay permiso que anular con él.
   */
  anular: (id: number, motivo: string) =>
    api.patch<ApiResponse<CertificadoMedico>>(
      `/dispensario/certificados-medicos/${id}/anular`,
      { motivo_anulacion: motivo }
    ).then(r => r.data.datos),

  /** Va por axios y no por `window.open`: el endpoint pide el token. */
  descargarPdf: (id: number) =>
    api.get(`/dispensario/certificados-medicos/${id}/pdf`, {
      responseType: 'blob',
    }).then(r => r.data as Blob),
}
