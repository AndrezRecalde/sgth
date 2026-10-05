import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { DocumentoPostulante } from './convocatoriaService'

/** Los tipos que se piden en una postulación. El backend acepta texto libre. */
export const TIPOS_DOCUMENTO = [
  'Cédula de identidad',
  'Hoja de vida',
  'Título académico',
  'Certificado de capacitación',
  'Certificado laboral',
  'Otro',
]

const base = (convocatoriaId: number, postulanteId: number) =>
  `/seleccion/convocatorias/${convocatoriaId}/postulantes/${postulanteId}/documentos`

/**
 * Los documentos del postulante (2026-10-05). Están en el disco privado desde
 * el 2026-10-04: se bajan por el API, que autoriza, y no por /storage.
 */
export const documentoPostulanteService = {
  subir: (convocatoriaId: number, postulanteId: number, tipo: string, archivo: File) => {
    const datos = new FormData()
    datos.append('tipo', tipo)
    datos.append('archivo', archivo)
    return api.post<ApiResponse<DocumentoPostulante>>(
      base(convocatoriaId, postulanteId), datos,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    ).then(r => r.data.datos)
  },

  descargar: (convocatoriaId: number, postulanteId: number, documentoId: number) =>
    api.get<Blob>(`${base(convocatoriaId, postulanteId)}/${documentoId}`, {
      responseType: 'blob',
    }).then(r => r.data),

  eliminar: (convocatoriaId: number, postulanteId: number, documentoId: number) =>
    api.delete<ApiResponse<unknown>>(
      `${base(convocatoriaId, postulanteId)}/${documentoId}`
    ).then(r => r.data.datos),
}
