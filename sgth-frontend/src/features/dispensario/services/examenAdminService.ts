import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { AreaExamen, CatalogoExamen, PerfilExamen } from './examenService'

export interface GuardarExamenCatalogoData {
  area:          string
  nombre:        string
  etiqueta_nota: string | null
  indicaciones:  string | null
  sensible:      boolean
  activo:        boolean
}

export interface GuardarPerfilExamenData {
  especialidad: PerfilExamen['especialidad']
  nombre:       string
  descripcion:  string | null
  ocupacional:  boolean
  activo:       boolean
  examenes:     number[]
}

/**
 * Lo que mantiene el catálogo y los perfiles: a diferencia de la solicitud,
 * trae también lo inactivo, para poder volver a activarlo.
 */
export const examenAdminService = {
  catalogo: () =>
    api.get<ApiResponse<CatalogoExamen[], { areas: AreaExamen[] }>>('/dispensario/catalogo-examenes', {
      params: { incluir_inactivos: 1 },
    }).then(r => ({
      examenes: r.data.datos ?? [],
      areas:    r.data.meta?.areas ?? [],
    })),

  /** Uno nuevo va al final de su área; editar admite solo una parte. */
  guardarExamen: (id: number | undefined, data: Partial<GuardarExamenCatalogoData>) =>
    (id
      ? api.patch<ApiResponse<CatalogoExamen>>(`/dispensario/catalogo-examenes/${id}`, data)
      : api.post<ApiResponse<CatalogoExamen>>('/dispensario/catalogo-examenes', data)
    ).then(r => r.data.datos),

  perfiles: () =>
    api.get<ApiResponse<PerfilExamen[]>>('/dispensario/perfiles-examen', {
      params: { incluir_inactivos: 1 },
    }).then(r => r.data.datos ?? []),

  guardarPerfil: (id: number | undefined, data: Partial<GuardarPerfilExamenData>) =>
    (id
      ? api.patch<ApiResponse<PerfilExamen>>(`/dispensario/perfiles-examen/${id}`, data)
      : api.post<ApiResponse<PerfilExamen>>('/dispensario/perfiles-examen', data)
    ).then(r => r.data.datos),
}
