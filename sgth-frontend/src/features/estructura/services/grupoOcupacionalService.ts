import api from '@/lib/axios'
import type { ApiResponse, GrupoOcupacional } from '@/types/api'

/** La escala de grupos ocupacionales: la LOSEP primero, de la RMU mayor a la menor. */
export const grupoOcupacionalService = {
  listar: () =>
    api.get<ApiResponse<GrupoOcupacional[]>>('/estructura/grupos-ocupacionales')
      .then(r => r.data.datos),
}
