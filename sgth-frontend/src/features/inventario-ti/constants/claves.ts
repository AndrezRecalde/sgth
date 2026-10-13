/**
 * Claves de consulta del inventario, del recurso a lo particular (regla 08).
 * `clavesInventario.raiz` invalida todo: un cambio de catálogo cambia lo que
 * muestran los listados y las fichas.
 */
export const clavesInventario = {
  raiz: ['inventario-ti'] as const,

  bienes: {
    todos: ['inventario-ti', 'bienes'] as const,
    lista: (filtros: unknown, page: number) => ['inventario-ti', 'bienes', 'lista', filtros, page] as const,
    uno: (id: number) => ['inventario-ti', 'bienes', id] as const,
    historial: (id: number) => ['inventario-ti', 'bienes', id, 'historial'] as const,
  },

  catalogos: {
    todos: ['inventario-ti', 'catalogos'] as const,
    formularios: ['inventario-ti', 'catalogos', 'formularios'] as const,
    tipos: ['inventario-ti', 'catalogos', 'tipos'] as const,
    marcas: ['inventario-ti', 'catalogos', 'marcas'] as const,
    ubicaciones: ['inventario-ti', 'catalogos', 'ubicaciones'] as const,
    contratos: ['inventario-ti', 'catalogos', 'contratos'] as const,
  },
}
