import api from '@/lib/axios'
import type { ApiResponse, MovimientoPersonal } from '@/types/api'

export type PersonaAusencia = {
  id: number | null
  nombre: string
  cedula: string | null
}

/** El contrato temporal que hoy cubre el hueco. Null si nadie lo cubre. */
export type ReemplazoAusencia = {
  contrato_id: number
  numero_contrato: string | null
  tipo_nombramiento: string | null
  desde: string | null
  hasta: string | null
  servidor: PersonaAusencia
}

/** El reintegro de la ausencia, en trámite o emitido (fase 2.4). */
export type ReintegroAusencia = {
  id: number
  estado: string
  codigo_registro: string | null
  fecha_regreso: string | null
}

export type AusenciaTemporal = {
  id: number
  codigo_registro: string | null
  tipo_movimiento: string | null
  subtipo_movimiento: string | null
  etiqueta: string | null
  desde: string | null
  /** Cuándo termina de verdad: la víspera del regreso si ya hay reintegro emitido. */
  hasta: string | null
  /** Null cuando la ausencia no tiene fecha de fin pactada. */
  dias_restantes: number | null
  reintegro: ReintegroAusencia | null
  servidor: PersonaAusencia
  unidad: string | null
  unidad_id: number | null
  puesto: string | null
  puesto_id: number | null
  destino: string | null
  reemplazo: ReemplazoAusencia | null
}

export type FiltrosAusencia = {
  fecha?: string
  cubiertas?: boolean
}

export type ReintegrarData = {
  fecha_regreso: string
  descripcion: string
}

export const ausenciaTemporalService = {
  listar: (filtros: FiltrosAusencia = {}) =>
    api
      .get<ApiResponse<AusenciaTemporal[]>>('/expediente/ausencias-temporales', {
        params: filtros,
      })
      .then((r) => r.data.datos),

  /** Prepara en borrador el reintegro: nace de la ausencia que cierra. */
  reintegrar: (ausenciaId: number, data: ReintegrarData) =>
    api
      .post<ApiResponse<MovimientoPersonal>>(`/expediente/movimientos/${ausenciaId}/reintegro`, data)
      .then((r) => r.data.datos),
}
