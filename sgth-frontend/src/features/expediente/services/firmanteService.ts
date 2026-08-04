import api from '@/lib/axios'
import type { ApiResponse, FirmanteVigente } from '@/types/api'

const BASE = '/expediente/firmantes-accion-personal'

export const firmanteService = {
  /**
   * Quiénes firmarán, derivado del organigrama. No hay designación manual:
   * para cambiar un firmante se cambia el jefe de la unidad anclada.
   */
  vigentes: (fecha?: string) =>
    api.get<ApiResponse<FirmanteVigente[]>>(`${BASE}/vigentes`, {
      params: fecha ? { fecha } : undefined,
    }).then((r) => r.data.datos),
}
