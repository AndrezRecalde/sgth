import api from '@/lib/axios'
import type { ApiResponse, Competencia, NivelCompetencia, PuestoCompetencia, TipoCompetencia } from '@/types/api'

export type CompetenciaParams = {
  tipo?:         TipoCompetencia
  solo_activas?: boolean
  buscar?:       string
}

export type CompetenciaDatos = Pick<
  Competencia,
  'tipo' | 'nombre' | 'definicion' | 'comportamiento_alto' | 'comportamiento_medio' | 'comportamiento_bajo'
>

/** El diccionario de competencias del Ministerio. */
export const competenciaService = {
  listar: (params?: CompetenciaParams) =>
    api.get<ApiResponse<Competencia[]>>('/estructura/competencias', { params })
      .then(r => r.data.datos),

  crear: (data: CompetenciaDatos) =>
    api.post<ApiResponse<Competencia>>('/estructura/competencias', data)
      .then(r => r.data.datos),

  actualizar: (id: number, data: Partial<Omit<CompetenciaDatos, 'tipo'> & Pick<Competencia, 'activo'>>) =>
    api.put<ApiResponse<Competencia>>(`/estructura/competencias/${id}`, data)
      .then(r => r.data.datos),

  eliminar: (id: number) =>
    api.delete<ApiResponse<null>>(`/estructura/competencias/${id}`)
      .then(r => r.data),
}

/** Las competencias que exige un puesto, con su nivel. */
export const puestoCompetenciaService = {
  listar: (puestoId: number) =>
    api.get<ApiResponse<PuestoCompetencia[]>>(`/estructura/puestos/${puestoId}/competencias`)
      .then(r => r.data.datos),

  agregar: (puestoId: number, data: { competencia_id: number; nivel: NivelCompetencia }) =>
    api.post<ApiResponse<PuestoCompetencia>>(`/estructura/puestos/${puestoId}/competencias`, data)
      .then(r => r.data.datos),

  cambiarNivel: (puestoId: number, id: number, nivel: NivelCompetencia) =>
    api.patch<ApiResponse<PuestoCompetencia>>(`/estructura/puestos/${puestoId}/competencias/${id}`, { nivel })
      .then(r => r.data.datos),

  quitar: (puestoId: number, id: number) =>
    api.delete<ApiResponse<null>>(`/estructura/puestos/${puestoId}/competencias/${id}`)
      .then(r => r.data),
}
