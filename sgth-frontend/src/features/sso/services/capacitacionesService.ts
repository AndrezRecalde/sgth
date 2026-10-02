import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { mapPaginado, type PaginadoParams, type RespuestaPaginada } from './paginado'
import type { CapacitacionSso, CapacitacionPayload } from './tipos'

/**
 * Capacitaciones en seguridad y salud.
 *
 * Alimentan dos de los cuatro índices proactivos del período —el número de
 * capacitaciones y el total de horas—, así que lo que no se registre aquí no
 * existe para el indicador.
 */
export const capacitacionesService = {
  listar: (params?: PaginadoParams & { estado?: boolean }) =>
    api.get<RespuestaPaginada<CapacitacionSso>>('/sso/capacitaciones', { params })
      .then(r => mapPaginado(r.data)),

  crear: (data: CapacitacionPayload) =>
    api.post<ApiResponse<CapacitacionSso>>('/sso/capacitaciones', data).then(r => r.data.datos),

  actualizar: (id: number, data: CapacitacionPayload) =>
    api.put<ApiResponse<CapacitacionSso>>(`/sso/capacitaciones/${id}`, data).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/capacitaciones/${id}`).then(r => r.data),
}
