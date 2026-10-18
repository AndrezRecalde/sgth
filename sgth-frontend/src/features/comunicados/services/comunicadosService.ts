import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type {
  Comunicado, ComunicadoAdjunto, ComunicadoPayload, FiltrosComunicados, SeleccionDestinatarios,
} from './tipos'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/comunicados'

/**
 * Las noticias y comunicados en manos de Talento Humano (`ComunicadoController`
 * y `ArchivosComunicadoController`). Lo que depende del estado —solo el borrador
 * se edita, solo lo publicado se retira— lo rechaza el backend con un 422.
 */
export const comunicadosService = {
  listar: (filtros: FiltrosComunicados, page: number, porPagina: number) =>
    api.get<ApiResponse<Comunicado[], MetaPaginada>>(URL, { params: { ...filtros, page, per_page: porPagina } })
      .then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  ver: (id: number) =>
    api.get<ApiResponse<Comunicado>>(`${URL}/${id}`).then((r) => r.data.datos),

  crear: (datos: ComunicadoPayload) =>
    api.post<ApiResponse<Comunicado>>(URL, datos).then((r) => r.data.datos),

  actualizar: (id: number, datos: ComunicadoPayload) =>
    api.put<ApiResponse<Comunicado>>(`${URL}/${id}`, datos).then((r) => r.data.datos),

  eliminar: (id: number) => api.delete(`${URL}/${id}`),

  /** El mensaje dice si salió ya o quedó programada. */
  publicar: (id: number) =>
    api.post<ApiResponse<Comunicado>>(`${URL}/${id}/publicar`)
      .then((r) => ({ comunicado: r.data.datos, mensaje: r.data.mensaje })),

  retirar: (id: number, motivo: string) =>
    api.post<ApiResponse<Comunicado>>(`${URL}/${id}/retirar`, { motivo }).then((r) => r.data.datos),

  duplicar: (id: number) =>
    api.post<ApiResponse<Comunicado>>(`${URL}/${id}/duplicar`).then((r) => r.data.datos),

  /** Lo único que cambia en lo publicado; se manda solo lo que cambia. */
  actualizarPublicacion: (id: number, cambios: { fijado?: boolean; publicado_hasta?: string | null }) =>
    api.patch<ApiResponse<Comunicado>>(`${URL}/${id}/publicacion`, cambios).then((r) => r.data.datos),

  contarDestinatarios: (seleccion: SeleccionDestinatarios) =>
    api.post<ApiResponse<{ total: number }>>(`${URL}/destinatarios/conteo`, seleccion)
      .then((r) => r.data.datos.total),

  agregarAdjunto: (id: number, archivo: File) => {
    const datos = new FormData()
    datos.append('archivo', archivo)
    return api.post<ApiResponse<ComunicadoAdjunto>>(`${URL}/${id}/adjuntos`, datos).then((r) => r.data.datos)
  },

  quitarAdjunto: (id: number, adjuntoId: number) => api.delete(`${URL}/${id}/adjuntos/${adjuntoId}`),

  descargarAdjunto: (id: number, adjuntoId: number) =>
    api.get<Blob>(`${URL}/${id}/adjuntos/${adjuntoId}`, { responseType: 'blob' }).then((r) => r.data),
}
