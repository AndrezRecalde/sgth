import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { BienInformatico, FiltrosBienes, GuardarBienData, HistorialBien } from './tipos'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/inventario/bienes'

/** La ficha de los equipos del inventario (`BienInformaticoController`). */
export const bienesService = {
  listar: (filtros: FiltrosBienes, page: number) =>
    api.get<ApiResponse<BienInformatico[], MetaPaginada>>(URL, { params: { ...filtros, page, per_page: 15 } })
      .then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  obtener: (id: number) =>
    api.get<ApiResponse<BienInformatico>>(`${URL}/${id}`).then((r) => r.data.datos),

  historial: (id: number) =>
    api.get<ApiResponse<HistorialBien>>(`${URL}/${id}/historial`).then((r) => r.data.datos),

  crear: (data: GuardarBienData) =>
    api.post<ApiResponse<BienInformatico>>(URL, data).then((r) => r.data.datos),

  actualizar: (id: number, data: Partial<GuardarBienData>) =>
    api.put<ApiResponse<BienInformatico>>(`${URL}/${id}`, data).then((r) => r.data.datos),

  retirar: (id: number) => api.delete(`${URL}/${id}`),

  /** La etiqueta con QR de un equipo (fase 1.6). */
  etiqueta: (id: number) =>
    api.get<Blob>(`${URL}/${id}/etiqueta`, { responseType: 'blob' }).then((r) => r.data),

  /** Las etiquetas del filtro del listado, en hojas A4. */
  etiquetas: (filtros: FiltrosBienes) =>
    api.get<Blob>('/inventario/etiquetas', { params: filtros, responseType: 'blob' }).then((r) => r.data),
}
