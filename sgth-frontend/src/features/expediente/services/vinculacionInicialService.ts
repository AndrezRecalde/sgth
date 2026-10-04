import api from '@/lib/axios'
import type { ApiResponse, ServidorConRelaciones } from '@/types/api'
import type { VinculacionInicialFormData } from '../schemas/vinculacionInicial.schema'

export const vinculacionInicialService = {
  registrar: (data: VinculacionInicialFormData) =>
    api
      .post<ApiResponse<ServidorConRelaciones>>('/expediente/vinculacion-inicial', data)
      .then((r) => r.data.datos),
}
