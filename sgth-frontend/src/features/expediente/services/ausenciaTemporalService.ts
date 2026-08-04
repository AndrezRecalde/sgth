import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

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

export type AusenciaTemporal = {
  id: number
  codigo_registro: string | null
  tipo_movimiento: string | null
  subtipo_movimiento: string | null
  etiqueta: string | null
  desde: string | null
  hasta: string | null
  /** Null cuando la ausencia no tiene fecha de fin pactada. */
  dias_restantes: number | null
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

export const ausenciaTemporalService = {
  listar: (filtros: FiltrosAusencia = {}) =>
    api
      .get<ApiResponse<AusenciaTemporal[]>>('/expediente/ausencias-temporales', {
        params: filtros,
      })
      .then((r) => r.data.datos),
}
