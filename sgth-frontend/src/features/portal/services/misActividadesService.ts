import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type CategoriaActividad =
  | 'reunion' | 'visita_campo' | 'elaboracion_documentos' | 'coordinacion'
  | 'capacitacion' | 'atencion_ciudadana' | 'otro'

/** Una actividad que el servidor anotó en su portal (`ActividadLaboralResource`). */
export type ActividadLaboral = {
  id: number
  fecha: string
  descripcion: string
  categoria: CategoriaActividad
  categoria_etiqueta: string
  producto_entregable: string | null
  /** Solo las del mes en curso: las de antes quedan como se informaron. */
  puede_editar: boolean
}

export type GuardarActividadData = Omit<
  ActividadLaboral, 'id' | 'categoria_etiqueta' | 'puede_editar'
>

export type FiltrosActividades = {
  desde?: string
  hasta?: string
  categoria?: CategoriaActividad
  buscar?: string
}

type MetaActividades = { total: number; pagina_actual: number }

const URL = '/autoservicio/mis-actividades'

export const misActividadesService = {
  listar: (filtros: FiltrosActividades, page: number) =>
    api.get<ApiResponse<ActividadLaboral[], MetaActividades>>(URL, { params: { ...filtros, page, per_page: 15 } })
      .then((r) => ({
        data: r.data.datos ?? [],
        total: r.data.meta?.total ?? 0,
      })),

  crear: (data: GuardarActividadData) =>
    api.post<ApiResponse<ActividadLaboral>>(URL, data).then((r) => r.data.datos),

  actualizar: (id: number, data: GuardarActividadData) =>
    api.put<ApiResponse<ActividadLaboral>>(`${URL}/${id}`, data).then((r) => r.data.datos),

  eliminar: (id: number) => api.delete(`${URL}/${id}`),

  pdf: (filtros: FiltrosActividades) =>
    api.get<Blob>(`${URL}/pdf`, { params: filtros, responseType: 'blob' }).then((r) => r.data),
}
