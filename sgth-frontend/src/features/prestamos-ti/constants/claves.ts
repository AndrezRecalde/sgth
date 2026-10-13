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
}
