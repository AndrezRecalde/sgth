import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

/** Un bloque de factores dentro de una categoría. */
export interface GrupoRiesgo {
  /** Solo «De seguridad» las usa; en el resto viaja `null`. */
  subcategoria: string | null
  etiqueta: string | null
  factores: string[]
}

export interface CategoriaRiesgoMsp {
  etiqueta: string
  grupos: GrupoRiesgo[]
}

/** Clave de categoría → su definición. El orden es el del formulario impreso. */
export type CatalogoRiesgos = Record<string, CategoriaRiesgoMsp>

/**
 * Catálogo oficial de la sección G del formulario 028 del MSP.
 *
 * Se pide al backend en vez de llevarlo escrito aquí porque el mismo catálogo
 * valida al guardar y alimenta el PDF: si viviera en dos sitios, acabarían
 * discrepando. Ya pasó — la lista que tenía el frontend traía 32 factores con
 * nombres que no existen en el formulario.
 */
export const catalogoRiesgosService = {
  obtener: () =>
    api
      .get<ApiResponse<CatalogoRiesgos>>('/dispensario/fichas-sso/catalogo-riesgos')
      .then((r) => r.data.datos),
}
