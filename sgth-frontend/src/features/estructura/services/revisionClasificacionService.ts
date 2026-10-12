import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { LoteAccionDetalle } from '@/features/expediente/services/contratoColectivoService'

/**
 * La revisión de la clasificación de puestos ocupados (LOSEP Arts. 61 y 62;
 * fase 7.1b): revalorización, reclasificación o ubicación, en un lote con una
 * acción por ocupante, que se registra entero en Acciones de Personal.
 */
export type CausalRevision = 'revalorizacion' | 'reclasificacion' | 'ubicacion'

export type PuestoDeLaRevision = {
  puesto_id: number
  grupo_ocupacional_id?: number | null
  cargo_id?: number | null
}

export type VistaPreviaRevision = {
  puestos: {
    puesto_id: number
    unidad: string | null
    antes: { denominacion: string | null; grado: string | null; rmu: number | null }
    despues: { denominacion: string | null; grado: string | null; rmu: number | null }
    ocupantes: {
      servidor_id: number
      nombre: string
      tipo_nombramiento: string | null
      remuneracion_actual: number | null
      remuneracion_nueva: number | null
    }[]
    impedimentos: string[]
  }[]
  /** Si sube la remuneración de alguien: entonces pide el dictamen. */
  sube_la_remuneracion: boolean
  causales: { value: CausalRevision; label: string }[]
}

export type RevisionClasificacionData = {
  causal: CausalRevision
  puestos: PuestoDeLaRevision[]
  documento_origen: string
  dictamen_presupuestario_ref?: string | null
  descripcion: string
  fecha_efectiva: string
}

const URL = '/expediente/lotes-accion/revision-clasificacion'

export const revisionClasificacionService = {
  previsualizar: (causal: CausalRevision, puestos: PuestoDeLaRevision[]) =>
    api
      .post<ApiResponse<VistaPreviaRevision>>(`${URL}/previsualizar`, { causal, puestos })
      .then((r) => r.data.datos),

  preparar: (data: RevisionClasificacionData) =>
    api.post<ApiResponse<LoteAccionDetalle>>(URL, data).then((r) => r.data.datos),
}
