import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { EstadoCampaniaSso } from '../constants/campania'

export interface CampaniaAssist {
  id: number
  periodo: string
  unidad_administrativa_id: number | null
  codigo_acceso: string
  fecha_apertura: string
  fecha_cierre: string | null
  activa: boolean
  creado_por: number
  /**
   * El estado real de la ventana, calculado en el servidor a partir de
   * `activa`, `fecha_apertura` y `fecha_cierre`.
   *
   * La pantalla pintaba `activa ? 'Abierta' : 'Cerrada'`, y `activa` solo
   * dice si alguien la cerró a mano: una campaña con la apertura en el
   * futuro salía «Abierta» y el enlace público rechazaba a quien entraba.
   */
  estado_campania: EstadoCampaniaSso
  unidad_administrativa?: { id: number; nombre: string } | null
  respuestas_count?: number
}

export interface SustanciaAssistInfo {
  codigo: string
  etiqueta: string
  ejemplos: string
  incluye_pregunta_5: boolean
}

export interface PreguntaAssist {
  codigo: string
  texto: string
  tipo: 'si_no' | 'frecuencia_3m' | 'frecuencia_vida'
  aplicaTabaco: boolean
}

export interface PreguntaInyectableAssist {
  codigo: string
  texto: string
}

export interface CuestionarioAssist {
  evaluacion: CampaniaAssist
  sustancias: Record<string, SustanciaAssistInfo>
  preguntas: Record<number, PreguntaAssist>
  pregunta_inyectable: PreguntaInyectableAssist
  opciones_frecuencia_3m: Record<string, string>
  opciones_frecuencia_vida: Record<string, string>
}

export interface RespuestaSustanciaAssist {
  p2: string
  p3?: string
  p4?: string
  p5?: string
  p6: string
  p7: string
}

export interface RespuestaAssistPayload {
  sustancias: Record<string, RespuestaSustanciaAssist>
  uso_inyectable?: string
}

export type NivelRiesgoAssist = 'bajo' | 'moderado' | 'alto'

export interface ResultadoSustanciaAgregado {
  etiqueta: string
  total_consumieron: number
  bajo: number
  moderado: number
  alto: number
}

export interface ResultadosAssist {
  evaluacion: CampaniaAssist
  total_respuestas: number
  sin_consumo_reportado: number
  riesgo_alto_alguna_sustancia: number
  uso_inyectable_reciente: number
  por_sustancia: Record<string, ResultadoSustanciaAgregado>
}

export const assistService = {
  // ── Gestión de campañas (protegido, Talento Humano / SSO) ───────────
  listarCampanias: (params?: { periodo?: string }) =>
    api.get<ApiResponse<CampaniaAssist[]>>('/sso/assist/campanias', { params })
      .then(r => r.data.datos ?? []),

  crearCampania: (data: { periodo: string; unidad_administrativa_id?: number | null; fecha_apertura: string; fecha_cierre?: string | null }) =>
    api.post<ApiResponse<CampaniaAssist>>('/sso/assist/campanias', data).then(r => r.data.datos),

  cerrarCampania: (id: number) =>
    api.patch<ApiResponse<CampaniaAssist>>(`/sso/assist/campanias/${id}/cerrar`).then(r => r.data.datos),

  obtenerResultados: (id: number) =>
    api.get<ApiResponse<ResultadosAssist>>(`/sso/assist/campanias/${id}/resultados`).then(r => r.data.datos),

  // ── Cuestionario público (anónimo, sin autenticación) ───────────────
  obtenerCuestionarioPublico: (codigo: string) =>
    api.get<ApiResponse<CuestionarioAssist>>(`/sso/assist/${codigo}/cuestionario`).then(r => r.data.datos),

  enviarRespuestaPublica: (codigo: string, data: RespuestaAssistPayload) =>
    api.post<ApiResponse<null>>(`/sso/assist/${codigo}/respuestas`, data).then(r => r.data),
}
