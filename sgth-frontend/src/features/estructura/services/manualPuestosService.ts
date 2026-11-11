import api from '@/lib/axios'
import type { ApiResponse, NivelCompetencia, TipoCompetencia } from '@/types/api'

/**
 * La revisión de las fichas del Manual de Puestos 2018-2019
 * (docs/diseno-manual-de-puestos.md, P4, PR 12). El backend arma estas
 * respuestas a mano, sin recurso, así que los tipos van aquí.
 */
export type EstadoFichaManual = 'lista' | 'para_revisar' | 'sin_puesto' | 'sin_unidad' | 'cargada'

/** La unidad de hoy de una gestión del manual: la que eligió TH o la sugerida por el nombre. */
export type UnidadDelManual = { id: number; nombre: string; origen: 'elegida' | 'sugerida' }

export type FichaManual = {
  id: number
  archivo: string
  proceso: string
  denominacion: string | null
  unidad: string | null
  gestion: string
  subproceso: string | null
  grupo_ocupacional: string | null
  grado: number | null
  plazas: number
  avisos_count: number
  estado: EstadoFichaManual
  /** Por qué está en ese estado, para quien revisa. */
  motivo: string
  unidad_sgth: UnidadDelManual | null
  /** El sugerido o, si ya se cargó, el puesto en que se cargó. */
  puesto: { id: number; cargo: string | null; unidad: string | null; grado: number | null; eliminado: boolean } | null
  cargada_en: string | null
  cargada_por: string | null
}

export type FichasManual = { fichas: FichaManual[]; resumen: Record<EstadoFichaManual, number> }

/** Lo que un puesto ya tiene y la carga reemplaza. */
export type ContenidoPuesto = { mision: boolean; requisitos: number; actividades: number; competencias: number }

export type CandidatoFicha = {
  puesto_id: number
  cargo: string
  grado: number | null
  grupo: string | null
  plazas: number | null
  puntaje: number
  coincide: { rol: boolean; grado: boolean; especialidad: boolean }
  /** Otra ficha ya cargada en ese puesto: hay que quitarla antes. */
  ficha_cargada: { id: number; archivo: string } | null
  contenido: ContenidoPuesto
}

export type PropuestaFicha = {
  mision: string | null
  relaciones: string | null
  nivel: 'profesional' | 'no_profesional' | null
  area_conocimiento: string | null
  especificidad_experiencia: string | null
  capacitacion: string | null
  subproceso: string | null
  requisitos: {
    nivel_instruccion: string | null
    detalle: string | null
    meses_experiencia: number | null
    /** Como lo escribe la ficha. */
    texto: string
    experiencia: string | null
  }[]
  actividades: { descripcion: string; conocimientos: string | null }[]
  competencias: { competencia_id: number; tipo: TipoCompetencia; nombre: string; nivel: NivelCompetencia; numero: number }[]
}

export type FichaManualDetalle = FichaManual & {
  avisos: string[]
  propuesta: PropuestaFicha
  ficha: { nivel: string | null; rol: string | null; ambito: string | null }
  candidatos: CandidatoFicha[]
  contenido_cargado: ContenidoPuesto | null
}

export type GestionDelManual = { gestion: string; nombre: string; fichas: number; unidad: UnidadDelManual | null }

const URL = '/estructura/manual-de-puestos'

export const manualPuestosService = {
  fichas: () => api.get<ApiResponse<FichasManual>>(`${URL}/fichas`).then((r) => r.data.datos),

  ficha: (id: number) => api.get<ApiResponse<FichaManualDetalle>>(`${URL}/fichas/${id}`).then((r) => r.data.datos),

  /** Devuelve la ficha ya cargada y el mensaje con lo que se cargó. */
  cargar: (id: number, puestoId: number) =>
    api.post<ApiResponse<FichaManualDetalle>>(`${URL}/fichas/${id}/cargar`, { puesto_id: puestoId }).then((r) => r.data),

  desvincular: (id: number) =>
    api.delete<ApiResponse<FichaManualDetalle>>(`${URL}/fichas/${id}/puesto`).then((r) => r.data.datos),

  equivalencias: () => api.get<ApiResponse<GestionDelManual[]>>(`${URL}/equivalencias`).then((r) => r.data.datos),

  /** Sin unidad, la gestión vuelve a la sugerida. */
  guardarEquivalencia: (gestion: string, unidadId: number | null) =>
    api.put<ApiResponse<GestionDelManual[]>>(`${URL}/equivalencias`, { gestion, unidad_administrativa_id: unidadId })
      .then((r) => r.data.datos),
}
