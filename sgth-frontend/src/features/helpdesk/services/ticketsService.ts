import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { CatalogosHelpdesk, ComentarioTicket, FiltrosTickets, NuevoTicketData, Ticket } from './tipos'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/helpdesk/tickets'

/** Con archivo, el formulario va como `multipart/form-data`; sin él, los campos vacíos no se mandan. */
function formulario(campos: Record<string, string | number | File | null | undefined>): FormData {
  const form = new FormData()
  Object.entries(campos).forEach(([clave, valor]) => {
    if (valor !== null && valor !== undefined && valor !== '') form.append(clave, valor instanceof File ? valor : String(valor))
  })
  return form
}

/** Los tickets de la mesa de ayuda (`TicketController`, `ComentarioTicketController`, fase 3.1). */
export const ticketsService = {
  listar: (filtros: FiltrosTickets, page: number) =>
    api.get<ApiResponse<Ticket[], MetaPaginada>>(URL, { params: { ...filtros, propios: filtros.propios ? 1 : undefined, page, per_page: 15 } })
      .then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  obtener: (id: number) =>
    api.get<ApiResponse<Ticket>>(`${URL}/${id}`).then((r) => r.data.datos),

  catalogos: () =>
    api.get<ApiResponse<CatalogosHelpdesk>>(`${URL}/catalogos`).then((r) => r.data.datos),

  crear: (data: NuevoTicketData) =>
    api.post<ApiResponse<Ticket>>(URL, formulario({ ...data })).then((r) => r.data.datos),

  comentar: (id: number, comentario: string, archivo: File | null, esInterno = false) =>
    api.post<ApiResponse<ComentarioTicket>>(`${URL}/${id}/comentarios`, formulario({ comentario, archivo, es_interno: esInterno ? 1 : undefined }))
      .then((r) => r.data.datos),

  archivo: (id: number, comentarioId: number) =>
    api.get<Blob>(`${URL}/${id}/comentarios/${comentarioId}/archivo`, { responseType: 'blob' }).then((r) => r.data),

  confirmar: (id: number, calificacion: number | null, comentario: string | null) =>
    api.post<ApiResponse<Ticket>>(`${URL}/${id}/confirmar`, { calificacion, comentario }).then((r) => r.data.datos),

  reabrir: (id: number, motivo: string) =>
    api.post<ApiResponse<Ticket>>(`${URL}/${id}/reabrir`, { motivo }).then((r) => r.data.datos),

  anular: (id: number, motivo: string) =>
    api.post<ApiResponse<Ticket>>(`${URL}/${id}/anular`, { motivo }).then((r) => r.data.datos),
}
