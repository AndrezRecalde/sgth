/**
 * Un importe del API, listo para leer. Llega como cadena decimal de Postgres o
 * como número según el endpoint, y un valor ausente se dice con guion.
 *
 * Estaba declarado dos veces —en el cajón de detalle y en el modal de dictamen—
 * con el mismo cuerpo.
 */
export function dinero(v?: string | number | null): string {
  return v != null ? `$ ${Number(v).toFixed(2)}` : '—'
}

/** Igual, pero devuelve null en vez de guion: para `DetailList`, que ya lo pinta. */
export function dineroONulo(v?: string | number | null): string | null {
  return v != null ? `$ ${Number(v).toFixed(2)}` : null
}
