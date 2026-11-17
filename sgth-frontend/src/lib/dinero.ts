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

const CON_MILES = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/**
 * Como `dinero`, con coma de miles: «$ 1,234,567.89». Para los montos de una
 * institución entera, que pasan del millón y sin separador no se leen
 * (docs/diseno-masa-salarial.md, D11). El decimal sigue siendo el punto, como
 * en todo el sistema (2026-11-16), y por eso el formato es `en-US`: `es-EC`
 * pondría coma decimal.
 */
export function dineroConMiles(v?: string | number | null): string {
  return v != null ? `$ ${CON_MILES.format(Number(v))}` : '—'
}
