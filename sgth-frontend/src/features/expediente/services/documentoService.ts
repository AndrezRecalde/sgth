import api from '@/lib/axios'
import type { ApiResponse, DocumentoServidor } from '@/types/api'

export const documentoService = {
  listar: (servidorId: number) =>
    api
      .get<ApiResponse<DocumentoServidor[]>>(
        `/expediente/servidores/${servidorId}/documentos`,
      )
      .then((r) => r.data.datos ?? []),

  subir: (servidorId: number, formData: FormData) =>
    api
      .post<ApiResponse<DocumentoServidor>>(
        `/expediente/servidores/${servidorId}/documentos`, formData,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )
      .then((r) => r.data.datos),

  eliminar: (servidorId: number, documentoId: number) =>
    api
      .delete<ApiResponse<void>>(
        `/expediente/servidores/${servidorId}/documentos/${documentoId}`,
      )
      .then((r) => r.data),

  descargar: (servidorId: number, documentoId: number) =>
    api
      .get(
        `/expediente/servidores/${servidorId}/documentos/${documentoId}/descargar`,
        { responseType: 'blob' },
      )
      .then((r) => r.data),
}
