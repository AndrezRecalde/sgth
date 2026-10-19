/**
 * Claves de consulta de las noticias y comunicados, del recurso a lo
 * particular (regla 08). Una publicación cambia con cada acción, así que las
 * mutaciones invalidan `todas`.
 */
export const clavesComunicados = {
  todas: ['comunicados'] as const,
  lista: (filtros: unknown, page: number) => ['comunicados', 'lista', filtros, page] as const,
  detalle: (id: number) => ['comunicados', 'detalle', id] as const,
  conteo: (seleccion: unknown) => ['comunicados', 'conteo', seleccion] as const,
  resumenLecturas: (id: number) => ['comunicados', 'lecturas', id, 'resumen'] as const,
  lecturas: (id: number, filtros: unknown, page: number) => ['comunicados', 'lecturas', id, 'lista', filtros, page] as const,
}

/**
 * Las del Portal, en su propia raíz: las pide cualquier servidor —también la
 * campana, en todos los subsistemas— y abrir o confirmar una cambia el resumen.
 */
export const clavesMisComunicados = {
  todas: ['mis-comunicados'] as const,
  /** Todas las páginas del listado, para invalidarlas juntas. */
  listas: ['mis-comunicados', 'lista'] as const,
  lista: (filtros: unknown, page: number) => ['mis-comunicados', 'lista', filtros, page] as const,
  detalle: (id: number) => ['mis-comunicados', 'detalle', id] as const,
  resumen: ['mis-comunicados', 'resumen'] as const,
}
