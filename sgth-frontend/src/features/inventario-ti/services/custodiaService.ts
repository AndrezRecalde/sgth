import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { Asignacion, DevolucionData, EntregaData } from './tipos'

const URL = '/inventario/asignaciones'

/** La custodia de los equipos (`AsignacionBienController`, fase 1.3). */
export const custodiaService = {
  entregar: (data: EntregaData) =>
    api.post<ApiResponse<Asignacion>>(URL, data).then((r) => r.data.datos),

  traspasar: (id: number, data: EntregaData) =>
    api.post<ApiResponse<Asignacion>>(`${URL}/${id}/traspasar`, data).then((r) => r.data.datos),

  devolver: (id: number, data: DevolucionData) =>
    api.post<ApiResponse<Asignacion>>(`${URL}/${id}/devolver`, data).then((r) => r.data.datos),

  adjuntarMemorando: (id: number, archivo: File) => {
    const form = new FormData()
    form.append('archivo', archivo)
    return api.post<ApiResponse<Asignacion>>(`${URL}/${id}/memorando`, form).then((r) => r.data.datos)
  },

  memorando: (id: number) =>
    api.get<Blob>(`${URL}/${id}/memorando`, { responseType: 'blob' }).then((r) => r.data),

  acta: (id: number) =>
    api.get<Blob>(`${URL}/${id}/acta`, { responseType: 'blob' }).then((r) => r.data),
}

/** «Mis equipos» del Portal: siempre los del propio servidor. */
export const misEquiposService = {
  listar: () =>
    api.get<ApiResponse<Asignacion[]>>('/autoservicio/mis-equipos').then((r) => r.data.datos ?? []),

  acta: (id: number) =>
    api.get<Blob>(`/autoservicio/mis-equipos/${id}/acta`, { responseType: 'blob' }).then((r) => r.data),
}
