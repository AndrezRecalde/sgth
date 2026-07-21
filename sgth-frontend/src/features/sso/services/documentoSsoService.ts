import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type TipoDocumentableSso =
  | 'cumplimiento_normativa'
  | 'inspeccion_sso'
  | 'capacitacion_sso'
  | 'programa_drogas_seguimiento'

export interface DocumentoSso {
  id: number
  documentable_type: TipoDocumentableSso
  documentable_id: number
  nombre: string
  ruta_archivo: string
  tipo_mime: string
  tamano_bytes: number
  subido_por: number
  created_at: string
  subidor?: { id: number; nombre_completo?: string; usuario_ti?: string } | null
}

export const documentoSsoService = {
  listar: (tipo: TipoDocumentableSso, documentableId: number) =>
    api.get<ApiResponse<DocumentoSso[]>>('/sso/documentos', {
      params: { documentable_type: tipo, documentable_id: documentableId },
    }).then(r => r.data.datos ?? []),

  subir: (data: { documentable_type: TipoDocumentableSso; documentable_id: number; nombre: string; archivo: File }) => {
    const formData = new FormData()
    formData.append('documentable_type', data.documentable_type)
    formData.append('documentable_id', String(data.documentable_id))
    formData.append('nombre', data.nombre)
    formData.append('archivo', data.archivo)
    return api.post<ApiResponse<DocumentoSso>>('/sso/documentos', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data.datos)
  },

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/documentos/${id}`).then(r => r.data),

  obtenerEnlaceDescarga: (id: number) =>
    api.get<ApiResponse<{ url_firmada: string }>>(`/sso/documentos/${id}/generar-enlace`)
      .then(r => r.data.datos!.url_firmada),
}
