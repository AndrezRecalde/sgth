import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { mapPaginado, type PaginadoParams, type RespuestaPaginada } from './paginado'
import type { HorasTrabajadasPeriodo, IndicadoresProactivos, IndicadoresReactivos } from './tipos'

/**
 * Las horas trabajadas del período: el denominador de los tres índices del
 * CD 513, y el único dato del módulo que se carga a mano.
 *
 * No hay `actualizar`: el backend no acepta sobrescribir un período ya
 * cargado, porque ese total mueve los tres índices sin dejar rastro. Para
 * corregirlo se borra y se vuelve a cargar. El método existía aquí y no lo
 * llamaba ningún hook.
 */
export const horasTrabajadasService = {
  listar: (params?: PaginadoParams & { periodo?: string; unidad_administrativa_id?: number }) =>
    api.get<RespuestaPaginada<HorasTrabajadasPeriodo>>('/sso/horas-trabajadas', { params })
      .then(r => mapPaginado(r.data)),

  registrar: (data: { periodo: string; unidad_administrativa_id?: number; total_horas: number }) =>
    api.post<ApiResponse<HorasTrabajadasPeriodo>>('/sso/horas-trabajadas', data).then(r => r.data.datos),

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
