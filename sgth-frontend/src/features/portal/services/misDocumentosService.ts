import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/**
 * Un documento laboral del servidor en su portal (fase 6.3a): una acción de
 * personal desde que se le notifica, o su hoja de registro interno o constancia
 * de autoridad desde que se registra.
 */
export type MiDocumentoLaboral = {
  id: number
  codigo_registro: string
  accion: string
  causal: string | null
  instrumento: 'accion_personal' | 'documento_ct' | 'acto_autoridad' | string
  instrumento_etiqueta: string | null
  fecha_efectiva: string | null
  anulada: boolean
  motivo_anulacion: string | null
  fecha_notificacion: string | null
  notificacion_medio: 'portal' | 'personal' | null
  acuse_en: string | null
  /** Notificada por el portal y todavía sin su acuse. */
  puede_acusar: boolean
}

const URL = '/autoservicio/mis-documentos'

export const misDocumentosService = {
  listar: () => api.get<ApiResponse<MiDocumentoLaboral[]>>(URL).then((r) => r.data.datos ?? []),

  pdf: (id: number) => api.get<Blob>(`${URL}/${id}/pdf`, { responseType: 'blob' }).then((r) => r.data),

  acusar: (id: number) => api.post<ApiResponse<MiDocumentoLaboral>>(`${URL}/${id}/acuse`).then((r) => r.data.datos),
}
