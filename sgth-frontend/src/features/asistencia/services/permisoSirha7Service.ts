import api from '@/lib/axios'
import type {
  ApiResponse,
  PermisoServidor,
  PreviaSirha7,
  TipoPermisoSirha7,
} from '@/types/api'

/**
 * Aprobar un permiso registrándolo en Sirha7, el biométrico
 * (`PermisoSirha7Controller`). Aparte de `asistenciaService`, que ya pasaba
 * del límite de tamaño.
 */
export const permisoSirha7Service = {
  tipos: () =>
    api.get<ApiResponse<TipoPermisoSirha7[]>>('/asistencia/permisos/sirha7/tipos')
      .then(r => r.data.datos ?? []),

  previa: (id: number) =>
    api.get<ApiResponse<PreviaSirha7>>(`/asistencia/permisos/${id}/sirha7`)
      .then(r => r.data.datos),

  aprobar: (id: number, leaveId: number) =>
    api.post<ApiResponse<PermisoServidor>>(
      `/asistencia/permisos/${id}/aprobar-sirha7`, { leave_id: leaveId }
    ).then(r => r.data.datos),
}
