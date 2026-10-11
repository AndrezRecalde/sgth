import api from '@/lib/axios'
import type { ApiResponse, MovimientoPersonal } from '@/types/api'

/** Quien está en período de prueba (LOSEP 17 b.5; fase 3.2). */
export type PeriodoDePrueba = {
  contrato_id: number
  servidor: { id: number | null; nombre: string; cedula: string | null }
  puesto: string | null
  unidad: string | null
  desde: string | null
  /** El último día de los tres meses. */
  hasta: string | null
  dias_restantes: number | null
  /** Ya pasaron los tres meses: toca el nombramiento definitivo. */
  concluida: boolean
  definitivo: { id: number; estado: string; codigo_registro: string | null } | null
}

export const periodoDePruebaService = {
  listar: () =>
    api.get<ApiResponse<PeriodoDePrueba[]>>('/expediente/periodos-de-prueba').then((r) => r.data.datos),

  /** Prepara en borrador el nombramiento definitivo, cumplidos los tres meses. */
  prepararDefinitivo: (contratoId: number) =>
    api
      .post<ApiResponse<MovimientoPersonal>>(`/expediente/contratos/${contratoId}/nombramiento-definitivo`)
      .then((r) => r.data.datos),
}
