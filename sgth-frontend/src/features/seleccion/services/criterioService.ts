import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export type SeccionCriterio = 'meritos' | 'oposicion'
export type TipoInput = 'radio' | 'numero' | 'checklist'

export interface OpcionCriterio {
  id:          number
  criterio_id: number
  etiqueta:    string
  puntaje:     number
  orden:       number
}

export interface CriterioEvaluacion {
  id:              number
  convocatoria_id: number
  seccion:         SeccionCriterio
  nombre:          string
  descripcion?:    string | null
  puntaje_maximo:  number
  tipo_input:      TipoInput
  orden:           number
  activo:          boolean
  opciones:        OpcionCriterio[]
}

export interface CrearCriterioData {
  seccion:        SeccionCriterio
  nombre:         string
  descripcion?:   string | null
  puntaje_maximo: number
  tipo_input:     TipoInput
  opciones?:      { etiqueta: string; puntaje: number }[]
}

export interface CalificacionItem {
  criterio_id:     number
  opcion_id?:      number | null
  valor_numerico?: number | null
  observacion?:    string | null
}

export interface CalificacionPostulante {
  criterio_id:      number
  opcion_id?:       number | null
  valor_numerico?:  number | null
  puntaje_obtenido: number
  observacion?:     string | null
  criterio?:        CriterioEvaluacion
  opcion?:          OpcionCriterio | null
}

export const criterioService = {
  listar: (convocatoriaId: number) =>
    api.get<ApiResponse<CriterioEvaluacion[]>>(
      `/seleccion/convocatorias/${convocatoriaId}/criterios`
    ).then(r => r.data.datos),

  crear: (convocatoriaId: number, data: CrearCriterioData) =>
    api.post<ApiResponse<CriterioEvaluacion>>(
      `/seleccion/convocatorias/${convocatoriaId}/criterios`,
      data
    ).then(r => r.data.datos),

  actualizar: (
    convocatoriaId: number,
    criterioId: number,
    data: Partial<CrearCriterioData>
  ) =>
    api.patch<ApiResponse<CriterioEvaluacion>>(
      `/seleccion/convocatorias/${convocatoriaId}/criterios/${criterioId}`,
      data
    ).then(r => r.data.datos),

  eliminar: (convocatoriaId: number, criterioId: number) =>
    api.delete<ApiResponse<unknown>>(
      `/seleccion/convocatorias/${convocatoriaId}/criterios/${criterioId}`
    ).then(r => r.data.datos),

  obtenerCalificaciones: (
    convocatoriaId: number,
    postulanteId: number
  ) =>
    api.get<ApiResponse<{
      postulante: unknown
      calificaciones: Record<number, CalificacionPostulante>
    }>>(
      `/seleccion/convocatorias/${convocatoriaId}/postulantes/${postulanteId}/calificaciones`
    ).then(r => r.data.datos),

  guardarCalificaciones: (
    convocatoriaId: number,
    postulanteId: number,
    calificaciones: CalificacionItem[]
  ) =>
    api.post<ApiResponse<unknown>>(
      `/seleccion/convocatorias/${convocatoriaId}/postulantes/${postulanteId}/calificaciones`,
      { calificaciones }
    ).then(r => r.data.datos),
}
