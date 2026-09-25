import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import { mapPaginado, type PaginadoParams, type RespuestaPaginada } from './paginado'
import type { AccidenteTrabajo } from './tipos'

/** Accidentes e incidentes de trabajo: el numerador de los índices CD 513. */
export const accidentesService = {
  listar: (params?: PaginadoParams & { servidor_id?: number }) =>
    api.get<RespuestaPaginada<AccidenteTrabajo>>('/sso/accidentes', { params })
      .then(r => mapPaginado(r.data)),

  crear: (data: Partial<AccidenteTrabajo>) =>
    api.post<ApiResponse<AccidenteTrabajo>>('/sso/accidentes', data).then(r => r.data.datos),

  actualizar: (id: number, data: Partial<AccidenteTrabajo>) =>
    api.put<ApiResponse<AccidenteTrabajo>>(`/sso/accidentes/${id}`, data).then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/sso/accidentes/${id}`).then(r => r.data),
}
