import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface PuestoActividad {
  id:          number
  puesto_id:   number
  descripcion: string
  orden:       number
  activo:      boolean
}

export const puestoActividadService = {
  listar: (puestoId: number) =>
    api.get<ApiResponse<PuestoActividad[]>>(
      `/estructura/puestos/${puestoId}/actividades`
    ).then(r => r.data.datos),

  crear: (puestoId: number, descripcion: string) =>
    api.post<ApiResponse<PuestoActividad>>(
      `/estructura/puestos/${puestoId}/actividades`,
      { descripcion }
    ).then(r => r.data.datos),

  actualizar: (
    puestoId: number,
    actividadId: number,
    data: Partial<Pick<PuestoActividad, 'descripcion' | 'orden' | 'activo'>>
  ) =>
    api.patch<ApiResponse<PuestoActividad>>(
      `/estructura/puestos/${puestoId}/actividades/${actividadId}`,
      data
    ).then(r => r.data.datos),

  eliminar: (puestoId: number, actividadId: number) =>
    api.delete<ApiResponse<unknown>>(
      `/estructura/puestos/${puestoId}/actividades/${actividadId}`
    ).then(r => r.data.datos),

  reordenar: (puestoId: number, orden: number[]) =>
    api.post<ApiResponse<unknown>>(
      `/estructura/puestos/${puestoId}/actividades/reordenar`,
      { orden }
    ).then(r => r.data.datos),
}
