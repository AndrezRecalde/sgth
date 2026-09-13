import api from '@/lib/axios'
import type {
  ApiResponse, BandejaResumen, EtapaBandeja, FiltrosBandeja, ViaticoBandeja,
} from '@/types/api'

type MetaPaginado = { total: number; pagina_actual: number }

/** La bandeja de Financiero (`BandejaViaticoController`). */
export const bandejaService = {
  resumen: (filtros: FiltrosBandeja) =>
    api.get<ApiResponse<BandejaResumen>>(
      '/viaticos/bandeja/resumen', { params: filtros }
    ).then(r => r.data.datos),

  listar: (etapa: EtapaBandeja, filtros: FiltrosBandeja, page: number, vencidas: boolean) =>
    api.get<ApiResponse<ViaticoBandeja[], MetaPaginado>>('/viaticos/bandeja', {
      params: { ...filtros, etapa, page, per_page: 15, vencidas: vencidas ? 1 : undefined },
    }).then(r => ({
      data:  r.data.datos,
      total: r.data.meta?.total ?? 0,
    })),
}
