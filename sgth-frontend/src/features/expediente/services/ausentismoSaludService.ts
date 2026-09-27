import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface AusentismoSalud {
  /** Permisos por enfermedad, no días: ver el controlador del backend. */
  permisos: number
  meses: number
  desde: string
}

export const ausentismoSaludService = {
  obtener: (servidorId: number) =>
    api
      .get<ApiResponse<AusentismoSalud>>(
        `/expediente/servidores/${servidorId}/ausentismo-salud`,
      )
      .then((r) => r.data.datos),
}
