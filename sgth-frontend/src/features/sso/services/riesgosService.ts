import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { mapPaginado, type PaginadoParams, type RespuestaPaginada } from './paginado'
import type { FactorRiesgoCatalogo, RiesgoLaboral } from './tipos'

/** La matriz de riesgos NTP 330 y el catálogo de factores del que se alimenta. */
export const riesgosService = {
  listar: (params?: PaginadoParams & { puesto_id?: number }) =>
    api.get<RespuestaPaginada<RiesgoLaboral>>('/sso/riesgos', { params })
      .then(r => mapPaginado(r.data)),

  crear: (data: Partial<RiesgoLaboral>) =>
    api.post<ApiResponse<RiesgoLaboral>>('/sso/riesgos', data).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<RiesgoLaboral>) =>
    api.put<ApiResponse<RiesgoLaboral>>(`/sso/riesgos/${id}`, data).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/riesgos/${id}`).then(r => r.data),
}

export const factoresRiesgoService = {
  listar: (params?: { categoria?: string; search?: string; solo_activos?: boolean }) =>
    api.get<ApiResponse<FactorRiesgoCatalogo[]>>('/sso/factores-riesgo', { params })
      .then(r => r.data.datos ?? []),

  crear: (data: { nombre: string; categoria: string }) =>
    api.post<ApiResponse<FactorRiesgoCatalogo>>('/sso/factores-riesgo', data).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<{ nombre: string; categoria: string; activo: boolean }>) =>
    api.put<ApiResponse<FactorRiesgoCatalogo>>(`/sso/factores-riesgo/${id}`, data).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/factores-riesgo/${id}`).then(r => r.data),
}
