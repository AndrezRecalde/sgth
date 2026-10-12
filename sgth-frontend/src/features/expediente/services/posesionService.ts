import api from '@/lib/axios'
import type { ApiResponse, MovimientoPersonal } from '@/types/api'
import type { RegistrarPosesionData } from './movimiento.types'

/**
 * La posesión del cargo, o el día en que el ocasional se presentó a laborar
 * (Reglamento a la LOSEP, Art. 20; fase 6.4). Aparte de `movimientoService`,
 * que no pasa de 100 líneas (regla 02).
 */
export const posesionService = {
  registrar: (movimientoId: number, data: RegistrarPosesionData) =>
    api
      .post<ApiResponse<MovimientoPersonal>>(`/expediente/movimientos/${movimientoId}/posesion`, data)
      .then((r) => r.data.datos),
}
