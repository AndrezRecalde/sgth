/**
 * Claves de consulta de los consumibles (regla 08). Cuelgan de la raíz del
 * inventario: un cambio en los equipos (su modelo, su custodio) cambia lo que
 * muestran, y `clavesInventario.raiz` los invalida también.
 */
export const clavesConsumibles = {
  todos: ['inventario-ti', 'consumibles'] as const,
  lista: (filtros: unknown) => ['inventario-ti', 'consumibles', 'lista', filtros] as const,
  uno: (id: number) => ['inventario-ti', 'consumibles', id] as const,
  kardex: (id: number, desde: string | null, hasta: string | null) => ['inventario-ti', 'consumibles', id, 'kardex', desde, hasta] as const,
  delEquipo: (bienId: number) => ['inventario-ti', 'consumibles', 'equipo', bienId] as const,
  /** Las solicitudes a bodega (5.4c): despacharlas cambia el kárdex, así que cuelgan de lo mismo. */
  solicitudes: {
    todas: ['inventario-ti', 'consumibles', 'solicitudes'] as const,
    lista: (filtros: unknown, page: number) => ['inventario-ti', 'consumibles', 'solicitudes', 'lista', filtros, page] as const,
    una: (id: number) => ['inventario-ti', 'consumibles', 'solicitudes', id] as const,
  },
}
