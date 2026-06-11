import api from '@/lib/axios'
import type { ApiResponse, TramoViatico } from '@/types/api'

export type CrearTramoData = {
  tipo_tramo?:           'ida' | 'destino' | 'escala' | 'regreso' | null
  origen_tipo:           'nacional' | 'internacional'
  origen_provincia_id?:  number | null
  origen_canton_id?:     number | null
  origen_pais?:          string | null
  origen_ciudad:         string
  destino_tipo:          'nacional' | 'internacional'
  destino_provincia_id?: number | null
  destino_canton_id?:    number | null
  destino_pais?:         string | null
  destino_ciudad:        string
  empresa_transporte_id: number
  datetime_salida:       string
  datetime_llegada:      string
  orden?:                number
}

export const tramoService = {
  listar: (viaticoId: number) =>
    api.get<ApiResponse<TramoViatico[]>>(
      `/viaticos/${viaticoId}/tramos`
    ).then(r => r.data.datos ?? []),

  crear: (viaticoId: number, data: CrearTramoData) =>
    api.post<ApiResponse<TramoViatico>>(
      `/viaticos/${viaticoId}/tramos`, data
    ).then(r => r.data.datos),

  actualizar: (
    viaticoId: number,
    tramoId:   number,
    data:      Partial<CrearTramoData>
  ) =>
    api.put<ApiResponse<TramoViatico>>(
      `/viaticos/${viaticoId}/tramos/${tramoId}`, data
    ).then(r => r.data.datos),

  eliminar: (viaticoId: number, tramoId: number) =>
    api.delete<ApiResponse<void>>(
      `/viaticos/${viaticoId}/tramos/${tramoId}`
    ).then(r => r.data),
}
