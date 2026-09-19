import api from '@/lib/axios'
import type { ApiResponse, DeclaracionExportParams, DeclaracionJuramentada } from '@/types/api'
import type { DeclaracionFormData } from '../schemas/declaracion.schema'

const base = (servidorId: number) =>
  `/expediente/servidores/${servidorId}/declaraciones-juramentadas`

/**
 * La declaración viaja como multipart porque puede llevar el PDF escaneado.
 * PHP no lee el cuerpo multipart de un PUT, así que la edición va por POST
 * con `_method=PUT`, que Laravel enruta al mismo `update`.
 */
function comoFormData(data: DeclaracionFormData, documento: File | null, metodo?: 'PUT'): FormData {
  const fd = new FormData()
  fd.append('tipo_declaracion', data.tipo_declaracion)
  fd.append('fecha_declaracion', data.fecha_declaracion)
  fd.append('codigo_barras', data.codigo_barras)
  if (documento) fd.append('documento', documento)
  if (metodo) fd.append('_method', metodo)
  return fd
}

export const declaracionService = {
  listar: (servidorId: number) =>
    api
      .get<ApiResponse<DeclaracionJuramentada[]>>(base(servidorId))
      .then((r) => r.data.datos ?? []),

  crear: (servidorId: number, data: DeclaracionFormData, documento: File | null) =>
    api
      .post<ApiResponse<DeclaracionJuramentada>>(base(servidorId), comoFormData(data, documento))
      .then((r) => r.data.datos),

  editar: (servidorId: number, id: number, data: DeclaracionFormData, documento: File | null) =>
    api
      .post<ApiResponse<DeclaracionJuramentada>>(
        `${base(servidorId)}/${id}`,
        comoFormData(data, documento, 'PUT'),
      )
      .then((r) => r.data.datos),

  eliminar: (servidorId: number, id: number) =>
    api.delete<ApiResponse<void>>(`${base(servidorId)}/${id}`).then((r) => r.data),

  /** El PDF escaneado de una declaración. */
  documento: (servidorId: number, id: number) =>
    api
      .get<Blob>(`${base(servidorId)}/${id}/documento`, { responseType: 'blob' })
      .then((r) => r.data),

  /**
   * Archivo para la Contraloría de las declaraciones de un rango. Si no hay
   * ninguna, el backend responde JSON en vez del archivo: se devuelve `null`.
   */
  exportar: (servidorId: number, params: DeclaracionExportParams) =>
    api
      .get<Blob>(`${base(servidorId)}/exportar`, { params, responseType: 'blob' })
      .then((r) => (r.data.type.includes('json') ? null : r.data)),
}
