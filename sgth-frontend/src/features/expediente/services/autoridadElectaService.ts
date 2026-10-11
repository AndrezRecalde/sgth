import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/** Un acto del período de una autoridad, en trámite o emitido. */
type ActoDelPeriodo = { id: number; estado: string; codigo_registro: string | null; fecha_efectiva: string | null }

/** Una autoridad electa en funciones y su período (diseño, 9.14; fase 4.1). */
export type AutoridadElecta = {
  contrato_id: number
  servidor: { id: number | null; nombre: string; cedula: string | null }
  /** El puesto: Prefecto/a o Viceprefecto/a. */
  dignidad: string | null
  unidad: string | null
  desde: string | null
  /** El último día del período; ese día el vínculo se cierra solo. */
  hasta: string | null
  dias_restantes: number | null
  credencial: string | null
  inicio: ActoDelPeriodo | null
  /** Sin acto que lo diga, Talento Humano fija el fin del período. */
  puede_fijar_fin: boolean
  cese: (ActoDelPeriodo & { causal: string | null }) | null
  /** El período siguiente de la autoridad reelegida. */
  siguiente: ActoDelPeriodo | null
}

export const autoridadElectaService = {
  listar: () =>
    api.get<ApiResponse<AutoridadElecta[]>>('/expediente/autoridades-electas').then((r) => r.data.datos),

  fijarFinDelPeriodo: (contratoId: number, fechaFin: string) =>
    api
      .post<ApiResponse<{ contrato_id: number; fecha_fin: string | null }>>(
        `/expediente/contratos/${contratoId}/fin-del-periodo`,
        { fecha_fin: fechaFin },
      )
      .then((r) => r.data.datos),
}
