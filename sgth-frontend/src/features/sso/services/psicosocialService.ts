import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface CampaniaPsicosocial {
  id: number
  periodo: string
  unidad_administrativa_id: number | null
  codigo_acceso: string
  fecha_apertura: string
  fecha_cierre: string | null
  activa: boolean
  creado_por: number
  unidad_administrativa?: { id: number; nombre: string } | null
  respuestas_count?: number
}

export interface PreguntaPsicosocial {
  dimension: string
  subdimension: string | null
  texto: string
}

export type NivelRiesgoPsicosocial = 'alto' | 'medio' | 'bajo'

export interface DimensionPsicosocial {
  etiqueta: string
  items: number[]
  rangos: Record<NivelRiesgoPsicosocial, [number, number]>
}

export interface CuestionarioPsicosocial {
  evaluacion: CampaniaPsicosocial
  preguntas: Record<number, PreguntaPsicosocial>
  dimensiones: Record<string, DimensionPsicosocial>
  datos_generales_opciones: Record<string, Record<string, string>>
}

export interface RespuestaPsicosocialPayload {
  area_trabajo?: string
  nivel_instruccion?: string
  antiguedad?: string
  rango_edad?: string
  autoidentificacion_etnica?: string
  genero?: string
  respuestas: Record<number, number>
}

export interface ResultadoDimensionAgregado {
  etiqueta: string
  bajo: number
  medio: number
  alto: number
}

export interface ResultadosPsicosociales {
  evaluacion: CampaniaPsicosocial
  total_respuestas: number
  global: { bajo: number; medio: number; alto: number }
  por_dimension: Record<string, ResultadoDimensionAgregado>
}

export const psicosocialService = {
  // ── Gestión de campañas (protegido, Talento Humano) ─────────────────
  listarCampanias: (params?: { periodo?: string }) =>
    api.get<ApiResponse<CampaniaPsicosocial[]>>('/sso/psicosocial/campanias', { params })
      .then(r => r.data.datos ?? []),

  crearCampania: (data: { periodo: string; unidad_administrativa_id?: number | null; fecha_apertura: string; fecha_cierre?: string | null }) =>
    api.post<ApiResponse<CampaniaPsicosocial>>('/sso/psicosocial/campanias', data).then(r => r.data.datos),

  cerrarCampania: (id: number) =>
    api.patch<ApiResponse<CampaniaPsicosocial>>(`/sso/psicosocial/campanias/${id}/cerrar`).then(r => r.data.datos),

  obtenerResultados: (id: number) =>
    api.get<ApiResponse<ResultadosPsicosociales>>(`/sso/psicosocial/campanias/${id}/resultados`).then(r => r.data.datos),

  // ── Cuestionario público (anónimo, sin autenticación) ───────────────
  obtenerCuestionarioPublico: (codigo: string) =>
    api.get<ApiResponse<CuestionarioPsicosocial>>(`/sso/psicosocial/${codigo}/cuestionario`).then(r => r.data.datos),

  enviarRespuestaPublica: (codigo: string, data: RespuestaPsicosocialPayload) =>
    api.post<ApiResponse<null>>(`/sso/psicosocial/${codigo}/respuestas`, data).then(r => r.data),
}
