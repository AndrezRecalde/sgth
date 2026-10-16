import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type EstadoSolicitud = 'pendiente' | 'despachada' | 'anulada'

type Firmante = { nombre: string | null; cargo: string | null }

/** `SolicitudBodegaService::presentar` (fase 5.4c). */
export type SolicitudBodega = {
  id: number
  codigo: string
  fecha: string
  estado: EstadoSolicitud
  ticket_id: number | null
  ticket: string | null
  servidor_id: number
  servidor: string | null
  unidad: string | null
  equipo: { id: number; codigo_institucional: string; codigo_anterior: string | null; descripcion: string } | null
  motivo: string
  /** Sellados al generarla: el PDF se reimprime igual aunque cambien los jefes. */
  firmantes: { area: Firmante; tic: Firmante; administrativo: Firmante; solicitante: Firmante }
  items: {
    id: number
    consumible_id: number
    codigo_bodega: string | null
    descripcion: string | null
    guarda_tic: boolean
    cantidad_solicitada: number
    cantidad_despachada: number | null
  }[]
  creada_por: string | null
  creada_en: string | null
  fecha_despacho: string | null
  despachada_por: string | null
  anulada_en: string | null
  anulada_por: string | null
  motivo_anulacion: string | null
}

export type FiltrosSolicitudes = { estado?: EstadoSolicitud; ticket_id?: number; search?: string }

export type NuevaSolicitudData = {
  ticket_id: number | null
  servidor_id: number | null
  bien_informatico_id: number | null
  motivo: string
  items: { consumible_id: number; cantidad: number }[]
}

export type DespachoData = { fecha: string; items: { item_id: number; cantidad_despachada: number }[] }

type MetaPaginada = { total: number }

const URL = '/inventario/solicitudes-bodega'

/** La solicitud de materiales informáticos a bodega (`SolicitudBodegaController`, fase 5.4c). */
export const solicitudesService = {
  listar: (filtros: FiltrosSolicitudes, page: number) =>
    api.get<ApiResponse<SolicitudBodega[], MetaPaginada>>(URL, { params: { ...filtros, page, per_page: 15 } })
      .then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),
  obtener: (id: number) => api.get<ApiResponse<SolicitudBodega>>(`${URL}/${id}`).then((r) => r.data.datos),
  crear: (data: NuevaSolicitudData) => api.post<ApiResponse<SolicitudBodega>>(URL, data).then((r) => r.data.datos),
  despachar: (id: number, data: DespachoData) =>
    api.post<ApiResponse<SolicitudBodega>>(`${URL}/${id}/despachar`, data).then((r) => ({ solicitud: r.data.datos, mensaje: r.data.mensaje })),
  anular: (id: number, motivo: string) => api.post<ApiResponse<SolicitudBodega>>(`${URL}/${id}/anular`, { motivo }).then((r) => r.data.datos),
  pdf: (id: number) => api.get<Blob>(`${URL}/${id}/pdf`, { responseType: 'blob' }).then((r) => r.data),
}
