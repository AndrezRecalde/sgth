import api from '@/lib/axios'
import type { ApiResponse, AusenciaDelTitular, Subrogacion, SubrogacionParams } from '@/types/api'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'

export const subrogacionService = {
  /** Pendientes de aprobación + activas: lo que la pantalla administra. */
  listarVigentes: (params?: SubrogacionParams) =>
    api
      .get<{
        exito:   boolean
        mensaje: string
        datos:   Subrogacion[]
        meta: {
          total:         number
          pagina_actual: number
          por_pagina:    number
          ultima_pagina: number
        }
      }>('/expediente/subrogaciones/vigentes', { params })
      .then((r) => ({
        data:  r.data.datos ?? [],
        total: r.data.meta?.total ?? 0,
      })),

  listarPorServidor: (servidorId: number) =>
    api
      .get<ApiResponse<Subrogacion[]>>(`/expediente/subrogaciones/servidor/${servidorId}`)
      .then((r) => r.data.datos ?? []),

  /** Las ausencias del titular que no han terminado: de una de ellas sale la
   *  subrogación (fase 5.2). */
  ausenciasDelTitular: (servidorId: number) =>
    api
      .get<ApiResponse<AusenciaDelTitular[]>>('/expediente/subrogaciones/ausencias-del-titular', {
        params: { servidor_id: servidorId },
      })
      .then((r) => r.data.datos ?? []),

  registrar: (data: SubrogacionFormData) =>
    api
      .post<ApiResponse<Subrogacion>>('/expediente/subrogaciones', data)
      .then((r) => r.data.datos),

  finalizar: (id: number) =>
    api
      .put<ApiResponse<Subrogacion>>(`/expediente/subrogaciones/${id}/finalizar`)
      .then((r) => r.data.datos),

  cancelar: (id: number, motivo: string) =>
    api
      .put<ApiResponse<Subrogacion>>(`/expediente/subrogaciones/${id}/cancelar`, { motivo })
      .then((r) => r.data.datos),
}
