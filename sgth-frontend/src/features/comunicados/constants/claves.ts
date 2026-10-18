/**
 * Claves de consulta de las noticias y comunicados, del recurso a lo
 * particular (regla 08). Una publicación cambia con cada acción, así que las
 * mutaciones invalidan `todas`.
 */
export const clavesComunicados = {
  todas: ['comunicados'] as const,
  lista: (filtros: unknown, page: number) => ['comunicados', 'lista', filtros, page] as const,
  detalle: (id: number) => ['comunicados', 'detalle', id] as const,
  /** La portada cambia de archivo al reemplazarla: va con la fecha del registro. */
  portada: (id: number, version: string | null) => ['comunicados', 'portada', id, version] as const,
  conteo: (seleccion: unknown) => ['comunicados', 'conteo', seleccion] as const,
}
