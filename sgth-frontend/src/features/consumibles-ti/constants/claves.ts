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
}
