/**
 * La forma paginada que devuelve el API del SSO y su traducción a lo que
 * espera `SgthTable`.
 *
 * El backend responde `{ datos, meta: { pagina_actual, ultima_pagina, … } }` y
 * la tabla pide `{ data, total, current_page, last_page }`. La conversión
 * estaba escrita una vez dentro de `ssoService.ts`, que era el único archivo
 * de servicios del módulo; al partirlo en uno por dominio pasa a vivir aquí,
 * para no acabar con cinco copias.
 */

export interface PaginadoParams {
  page?: number
  por_pagina?: number
  estado?: boolean
}

export interface PaginadoResultado<T> {
  data: T[]
  total: number
  current_page: number
  last_page: number
}

export interface RespuestaPaginada<T> {
  exito: boolean
  mensaje: string
  datos: T[]
  meta: {
    pagina_actual: number
    por_pagina: number
    total: number
    ultima_pagina: number
    desde: number | null
    hasta: number | null
  }
}

export function mapPaginado<T>(respuesta: RespuestaPaginada<T>): PaginadoResultado<T> {
  return {
    data: respuesta.datos,
    total: respuesta.meta?.total ?? 0,
    current_page: respuesta.meta?.pagina_actual ?? 1,
    last_page: respuesta.meta?.ultima_pagina ?? 1,
  }
}
