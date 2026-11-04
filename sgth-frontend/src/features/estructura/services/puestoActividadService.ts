import api from '@/lib/axios'
import type { ApiResponse, PuestoActividad } from '@/types/api'

export type { PuestoActividad }

/** Lo que se escribe de una actividad: el FEMO usa la descripción; el FOR-02, todo. */
export type PuestoActividadDatos = Pick<PuestoActividad, 'descripcion' | 'producto' | 'conocimientos' | 'frecuencia'>

export const puestoActividadService = {
  listar: (puestoId: number) =>
    api.get<ApiResponse<PuestoActividad[]>>(
      `/estructura/puestos/${puestoId}/actividades`
    ).then(r => r.data.datos),

  crear: (puestoId: number, data: PuestoActividadDatos) =>
    api.post<ApiResponse<PuestoActividad>>(
      `/estructura/puestos/${puestoId}/actividades`,
      data
    ).then(r => r.data.datos),

  actualizar: (
    puestoId: number,
    actividadId: number,
    data: Partial<PuestoActividadDatos & Pick<PuestoActividad, 'orden' | 'activo'>>
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
