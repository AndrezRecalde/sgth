import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { EstadoSolicitudAccion, SolicitudAccion } from '@/features/portal/services/misSolicitudesService'

const URL = '/expediente/solicitudes-accion'

/** Las solicitudes del portal, del lado de Talento Humano (fase 6.3b). */
export const solicitudesAccionService = {
  listar: (estado?: EstadoSolicitudAccion | null) =>
    api
      .get<ApiResponse<SolicitudAccion[]>>(URL, { params: { estado: estado || undefined } })
      .then((r) => r.data.datos ?? []),

  preparar: (id: number) =>
    api.post<ApiResponse<SolicitudAccion>>(`${URL}/${id}/preparar`).then((r) => r.data.datos),

  rechazar: (id: number, motivo: string) =>
    api.post<ApiResponse<SolicitudAccion>>(`${URL}/${id}/rechazar`, { motivo }).then((r) => r.data.datos),

  documento: (id: number) => api.get<Blob>(`${URL}/${id}/documento`, { responseType: 'blob' }).then((r) => r.data),
}
