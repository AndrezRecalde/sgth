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
  /** Si el Consejo lo designó (COOTAD Art. 47 n), el número de la resolución. */
  designacion: string | null
  inicio: ActoDelPeriodo | null
  /** Sin acto que lo diga, Talento Humano fija el fin del período. */
  puede_fijar_fin: boolean
  cese: (ActoDelPeriodo & { causal: string | null }) | null
  /** El período siguiente de la autoridad reelegida. */
  siguiente: ActoDelPeriodo | null
  /** Quien entra a su puesto cuando termina (2026-10-21). */
  sucesor: (ActoDelPeriodo & { nombre: string }) | null
  /** La licencia del Consejo en curso o por venir (fase 4.1b). */
  ausencia: (ActoDelPeriodo & { desde: string | null; hasta: string | null }) | null
  /** Quién la subroga, o a quién subroga (fase 4.1b). */
  subrogacion: { rol: 'subrogado' | 'subrogante'; con: string; desde: string | null; hasta: string | null; estado: string } | null
  es_prefecto: boolean
}

/**
 * Una autoridad que entra: su período todavía no rige, así que no tiene
 * vínculo ni sale entre las autoridades en funciones (2026-10-21).
 */
export type AutoridadEntrante = {
  movimiento_id: number
  servidor: { id: number | null; nombre: string; cedula: string | null }
  dignidad: string | null
  unidad: string | null
  /** El día de la posesión: ese día empieza el período. */
  desde: string | null
  hasta: string | null
  credencial: string | null
  /** Si el Consejo lo designó (COOTAD Art. 47 n), el número de la resolución. */
  designacion: string | null
  inicio: ActoDelPeriodo
  /** La autoridad en funciones en ese puesto, y su último día. */
  sucede_a: { contrato_id: number; nombre: string; hasta: string | null } | null
  /** Por qué el relevo no cuadra: la saliente sin fin, o que termina tarde. */
  aviso: string | null
}

export type SubrogacionPorLeyData = {
  fecha_inicio: string
  fecha_fin: string
  motivo: string
  resolucion_numero: string
  observacion?: string | null
}

export const autoridadElectaService = {
  listar: () =>
    api.get<ApiResponse<AutoridadElecta[]>>('/expediente/autoridades-electas').then((r) => r.data.datos),

  entrantes: () =>
    api
      .get<ApiResponse<AutoridadEntrante[]>>('/expediente/autoridades-electas/entrantes')
      .then((r) => r.data.datos),

  /** La subrogación por ley del Viceprefecto, en borrador (COOTAD Art. 51; fase 4.1b). */
  registrarSubrogacionPorLey: (data: SubrogacionPorLeyData) =>
    api
      .post<ApiResponse<{ subrogacion_id: number; movimiento_id: number | null }>>(
        '/expediente/autoridades-electas/subrogacion-por-ley',
        data,
      )
      .then((r) => r.data.datos),

  fijarFinDelPeriodo: (contratoId: number, fechaFin: string) =>
    api
      .post<ApiResponse<{ contrato_id: number; fecha_fin: string | null }>>(
        `/expediente/contratos/${contratoId}/fin-del-periodo`,
        { fecha_fin: fechaFin },
      )
      .then((r) => r.data.datos),
}
