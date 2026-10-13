import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { AprobarBajaData, BajaBien, FiltrosBajas, ProponerBajaData } from './tiposBaja'

type MetaPaginada = { total: number; pagina_actual: number }

const URL = '/inventario/bajas'

/** La baja de los equipos (`BajaController`, fase 1.6). */
export const bajasService = {
  listar: (filtros: FiltrosBajas, page: number) =>
    api.get<ApiResponse<BajaBien[], MetaPaginada>>(URL, { params: { ...filtros, page, per_page: 15 } })
      .then((r) => ({ data: r.data.datos ?? [], total: r.data.meta?.total ?? 0 })),

  proponer: (data: ProponerBajaData) =>
    api.post<ApiResponse<BajaBien>>(URL, data).then((r) => r.data.datos),

  aprobar: (id: number, data: AprobarBajaData) =>
    api.post<ApiResponse<BajaBien>>(`${URL}/${id}/aprobar`, data).then((r) => r.data.datos),

  rechazar: (id: number, motivo: string) =>
    api.post<ApiResponse<BajaBien>>(`${URL}/${id}/rechazar`, { motivo }).then((r) => r.data.datos),

  adjuntarMemorando: (id: number, archivo: File) => {
    const form = new FormData()
    form.append('archivo', archivo)
    return api.post<ApiResponse<BajaBien>>(`${URL}/${id}/memorando`, form).then((r) => r.data.datos)
  },

  entregaActivos: (id: number, fecha: string) =>
    api.post<ApiResponse<BajaBien>>(`${URL}/${id}/entrega-activos`, { fecha }).then((r) => r.data.datos),

  memorando: (id: number) =>
    api.get<Blob>(`${URL}/${id}/memorando`, { responseType: 'blob' }).then((r) => r.data),

  informe: (id: number) =>
    api.get<Blob>(`${URL}/${id}/informe`, { responseType: 'blob' }).then((r) => r.data),
}
