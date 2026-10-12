import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { LoteAccionDetalle } from '@/features/expediente/services/contratoColectivoService'

/**
 * El traspaso de un puesto a otra unidad (LOSEP Art. 37; Reglamento a la LOSEP
 * Art. 69; fase 7.1a). Se prepara desde Estructura y nace como un lote de
 * Acciones de Personal: un acto por ocupante.
 */
export type VistaPreviaTraspaso = {
  puesto: { id: number; cargo: string | null; unidad: string | null; es_jefe: boolean }
  ocupantes: { servidor_id: number; nombre: string; cedula: string | null; tipo_nombramiento: string | null }[]
  /** Lo que impide prepararlo: vacante, sin acción de personal, trámites… */
  impedimentos: string[]
  /** Los criterios del Art. 69, numeral 1. */
  criterios: { value: string; label: string }[]
}

export type TraspasoPuestoData = {
  puesto_id: number
  unidad_destino_id: number
  criterio: string
  informe_uath_numero: string
  descripcion: string
  fecha_efectiva: string
}

const URL = '/expediente/lotes-accion/traspaso-puesto'

export const traspasoPuestoService = {
  previsualizar: (puestoId: number, unidadDestinoId?: number | null) =>
    api
      .post<ApiResponse<VistaPreviaTraspaso>>(`${URL}/previsualizar`, {
        puesto_id: puestoId,
        ...(unidadDestinoId ? { unidad_destino_id: unidadDestinoId } : {}),
      })
      .then((r) => r.data.datos),

  preparar: (data: TraspasoPuestoData) =>
    api.post<ApiResponse<LoteAccionDetalle>>(URL, data).then((r) => r.data.datos),
}
