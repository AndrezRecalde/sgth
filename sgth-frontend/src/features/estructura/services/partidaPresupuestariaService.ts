import api from '@/lib/axios'
import type {
  ApiResponse,
  PartidaPresupuestaria,
  PartidaPresupuestariaFormData,
  PartidaPresupuestariaParams,
} from '@/types/api'

const BASE = '/estructura/partidas-presupuestarias'

export const partidaPresupuestariaService = {
  /**
   * El catálogo es pequeño (decenas de filas) y se usa sobre todo para
   * alimentar selectores, así que se pide completo por defecto — el
   * backend pagina solo si no llega `all`.
   */
  listar: (params?: PartidaPresupuestariaParams) =>
    api.get<ApiResponse<PartidaPresupuestaria[]>>(
      BASE, { params: { all: true, ...params } }
    ).then(r => r.data.datos),

  crear: (data: PartidaPresupuestariaFormData) =>
    api.post<ApiResponse<PartidaPresupuestaria>>(
      BASE, data
    ).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<PartidaPresupuestariaFormData>) =>
    api.put<ApiResponse<PartidaPresupuestaria>>(
      `${BASE}/${id}`, data
    ).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<void>>(
      `${BASE}/${id}`
    ).then(r => r.data),
}
