import api from '@/lib/axios'
import type { ApiResponse, MasaSalarial, MasaSalarialParams } from '@/types/api'

export const masaSalarialService = {
  /**
   * La suma de las remuneraciones registradas en los vínculos vigentes a la
   * fecha de corte, por tipo, régimen y unidad, con su detalle. No es lo pagado.
   */
  resumen: (params: MasaSalarialParams) =>
    api.get<ApiResponse<MasaSalarial>>('/estructura/masa-salarial', { params })
      .then(r => r.data.datos),
}
