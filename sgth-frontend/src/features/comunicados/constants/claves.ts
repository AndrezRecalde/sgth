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
}
