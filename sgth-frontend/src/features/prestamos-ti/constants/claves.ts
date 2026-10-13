/**
 * Claves de consulta del préstamo de equipos (regla 08). Pedir, cancelar o
 * cualquier paso del trámite cambia la disponibilidad, así que se invalida
 * `raiz` entera.
 */
export const clavesPrestamos = {
  raiz: ['prestamos-ti'] as const,

  mios: {
    lista: (estado: unknown, page: number) => ['prestamos-ti', 'mios', estado, page] as const,
    uno: (id: number) => ['prestamos-ti', 'mios', 'uno', id] as const,
  },

  disponibilidad: (desde: string, hasta: string) => ['prestamos-ti', 'disponibilidad', desde, hasta] as const,
  reglas: ['prestamos-ti', 'reglas'] as const,

  /** Lo de TIC: la bandeja, su resumen, un préstamo y la agenda con nombres. */
  tic: {
    lista: (filtros: unknown, page: number) => ['prestamos-ti', 'tic', 'lista', filtros, page] as const,
    resumen: ['prestamos-ti', 'tic', 'resumen'] as const,
    uno: (id: number) => ['prestamos-ti', 'tic', 'uno', id] as const,
    agenda: (desde: string, hasta: string) => ['prestamos-ti', 'tic', 'agenda', desde, hasta] as const,
  },
}
