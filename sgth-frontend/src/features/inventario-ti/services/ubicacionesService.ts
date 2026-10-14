import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

const URL = '/inventario/ubicaciones-por-unidad'

/** La ubicación habitual de una unidad: solo se propone, no mueve equipos. */
export type UbicacionDeUnidad = {
  unidad_id: number
  unidad: string
  ubicacion_id: number | null
  ubicacion: string | null
}

export type DatosMudanza = {
  unidad_administrativa_id: number
  /** null: los equipos de la unidad que no tienen ubicación. */
  desde_ubicacion_id: number | null
  hacia_ubicacion_id: number
  aplicar: boolean
}

export type ResultadoMudanza = {
  total: number
  aplicada: boolean
  equipos: { id: number; codigo_institucional: string; equipo: string; custodio: string | null }[]
}

/** La ubicación habitual de cada unidad y la mudanza en lote (`UbicacionesBienController`). */
export const ubicacionesService = {
  porUnidad: () =>
    api.get<ApiResponse<UbicacionDeUnidad[]>>(URL).then((r) => r.data.datos ?? []),

  fijar: (unidadId: number, ubicacionId: number | null) =>
    api.put(`${URL}/${unidadId}`, { ubicacion_id: ubicacionId }),

  sugerida: (params: { unidad_administrativa_id?: number | null; servidor_id?: number | null }) =>
    api.get<ApiResponse<{ id: number; nombre: string } | null>>(`${URL}/sugerida`, { params }).then((r) => r.data.datos ?? null),

  mudanza: (data: DatosMudanza) =>
    api.post<ApiResponse<ResultadoMudanza>>(`${URL}/mudanza`, data).then((r) => r.data.datos),
}
