/**
 * Un importe del API, listo para leer. Llega como cadena decimal de Postgres o
 * como número según el endpoint, y un valor ausente se dice con guion.
 *
 * Estaba declarado dos veces —en el cajón de detalle y en el modal de dictamen—
 * con el mismo cuerpo. Vivía en `features/expediente/utils` y lo importaban
 * también Estructura: lo que usan dos módulos va en `lib/` (regla 02).
 */
export function dinero(v?: string | number | null): string {
  return v != null ? `$ ${Number(v).toFixed(2)}` : '—'
}
