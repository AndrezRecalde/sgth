import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { mapPaginado, type PaginadoParams, type RespuestaPaginada } from './paginado'
import type { HorasTrabajadasPeriodo, IndicadoresProactivos, IndicadoresReactivos } from './tipos'

/**
 * Las horas trabajadas del período: el denominador de los tres índices del
 * CD 513, y el único dato del módulo que se carga a mano.
 */
export const horasTrabajadasService = {
  listar: (params?: PaginadoParams & { periodo?: string; unidad_administrativa_id?: number }) =>
    api.get<RespuestaPaginada<HorasTrabajadasPeriodo>>('/sso/horas-trabajadas', { params })
      .then(r => mapPaginado(r.data)),

  registrar: (data: { periodo: string; unidad_administrativa_id?: number; total_horas: number }) =>
    api.post<ApiResponse<HorasTrabajadasPeriodo>>('/sso/horas-trabajadas', data).then(r => r.data.datos),

  actualizar: (id: number, data: { total_horas: number }) =>
    api.put<ApiResponse<HorasTrabajadasPeriodo>>(`/sso/horas-trabajadas/${id}`, data).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/horas-trabajadas/${id}`).then(r => r.data),
}

export const indicadoresService = {
  /** CD 513: frecuencia, gravedad y tasa de riesgo. */
  reactivos: (params: { periodo: string; unidad_administrativa_id?: number }) =>
    api.get<ApiResponse<IndicadoresReactivos>>('/sso/indicadores/reactivos', { params })
      .then(r => r.data.datos),

  /** Inspecciones, capacitaciones y cobertura de EPP. */
  proactivos: (params: { periodo: string; unidad_administrativa_id?: number }) =>
    api.get<ApiResponse<IndicadoresProactivos>>('/sso/indicadores/proactivos', { params })
      .then(r => r.data.datos),
}
