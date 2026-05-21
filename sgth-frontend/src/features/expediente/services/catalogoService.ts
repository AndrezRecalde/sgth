import api from '@/lib/axios'
import type { ApiResponse, Provincia, Canton } from '@/types/api'

export const catalogoService = {
  provincias: () =>
    api.get<ApiResponse<Provincia[]>>(
      '/catalogos/provincias'
    ).then(r => r.data.datos),

  cantones: (provinciaId: number) =>
    api.get<ApiResponse<Canton[]>>(
      `/catalogos/provincias/${provinciaId}/cantones`
    ).then(r => r.data.datos),
}
