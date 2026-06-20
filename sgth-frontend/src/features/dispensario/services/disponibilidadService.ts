import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export const disponibilidadService = {
  miEstado: () =>
    api.get<ApiResponse<{ disponible: boolean }>>(
      '/dispensario/disponibilidad/mi-estado'
    ).then(r => r.data.datos),

  alternar: () =>
    api.post<ApiResponse<{ disponible: boolean }>>(
      '/dispensario/disponibilidad/alternar'
    ).then(r => r.data.datos),
}
