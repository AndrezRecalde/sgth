import api from '@/lib/axios'
import type { ApiResponse, AutorizacionVuelo } from '@/types/api'

export const vueloService = {
  listar: () =>
    api.get<ApiResponse<AutorizacionVuelo[]>>(
      '/viaticos/vuelos'
    ).then(r => r.data.datos ?? []),

  aprobar: (id: number, data?: { observacion?: string }) =>
    api.post<ApiResponse<AutorizacionVuelo>>(
      `/viaticos/vuelos/${id}/aprobar`, data ?? {}
    ).then(r => r.data.datos),

  rechazar: (id: number, data: { observacion: string }) =>
    api.post<ApiResponse<AutorizacionVuelo>>(
      `/viaticos/vuelos/${id}/rechazar`, data
    ).then(r => r.data.datos),
}
