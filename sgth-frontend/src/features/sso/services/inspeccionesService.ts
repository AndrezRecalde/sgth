import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { mapPaginado, type PaginadoParams, type RespuestaPaginada } from './paginado'
import type { InspeccionSso, InspeccionPayload } from './tipos'

/**
 * Inspecciones de seguridad por unidad administrativa.
 *
 * Es uno de los cuatro índices proactivos del período: el backend las cuenta
 * en `calcularIndicadoresProactivos`, así que el indicador se alimenta de lo
 * que se registre aquí.
 */
export const inspeccionesService = {
  listar: (params?: PaginadoParams & { unidad_administrativa_id?: number; estado?: boolean }) =>
    api.get<RespuestaPaginada<InspeccionSso>>('/sso/inspecciones', { params })
      .then(r => mapPaginado(r.data)),

  crear: (data: InspeccionPayload) =>
    api.post<ApiResponse<InspeccionSso>>('/sso/inspecciones', data).then(r => r.data.datos),

  actualizar: (id: number, data: InspeccionPayload) =>
    api.put<ApiResponse<InspeccionSso>>(`/sso/inspecciones/${id}`, data).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/inspecciones/${id}`).then(r => r.data),
}
