import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { components } from '@/types/api.generated'

/**
 * La descripción del puesto, como la ficha del Manual de Puestos
 * (docs/diseno-manual-de-puestos.md, P1): misión, relaciones, requisitos de
 * instrucción y experiencia, capacitación y subproceso.
 */
export type DescripcionPuesto = components['schemas']['PuestoPerfilResource']
export type NivelInstruccion = components['schemas']['NivelInstruccion']
export type NivelDelPuesto = components['schemas']['NivelDelPuesto']

/** Lo que se guarda. Las alternativas, si van, reemplazan a las que había. */
export type DescripcionPuestoDatos = {
  mision: string | null
  relaciones: string | null
  nivel: NivelDelPuesto | null
  area_conocimiento: string | null
  especificidad_experiencia: string | null
  capacitacion: string | null
  subproceso: string | null
  requisitos: { nivel_instruccion: NivelInstruccion; detalle: string | null; meses_experiencia: number }[]
}

const url = (puestoId: number) => `/estructura/puestos/${puestoId}/perfil`

export const descripcionPuestoService = {
  obtener: (puestoId: number) =>
    api.get<ApiResponse<DescripcionPuesto>>(url(puestoId)).then((r) => r.data.datos),

  guardar: (puestoId: number, datos: DescripcionPuestoDatos) =>
    api.put<ApiResponse<DescripcionPuesto>>(url(puestoId), datos).then((r) => r.data.datos),

  /** La ficha «Descripción y perfil del puesto» en PDF, con las diez secciones del manual. */
  pdf: (puestoId: number) =>
    api.get<Blob>(`${url(puestoId)}/pdf`, { responseType: 'blob' }).then((r) => r.data),

  /** El manual de una gestión (P5, PR 14): portada, distribución de puestos y la ficha de cada uno. */
  manualDeGestion: (unidadId: number) =>
    api.get<Blob>(`/estructura/unidades-administrativas/${unidadId}/manual-de-puestos`, { responseType: 'blob' })
      .then((r) => r.data),
}
