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
}
