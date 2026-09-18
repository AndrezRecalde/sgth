/**
 * Un monto en dólares, con separador de miles y dos decimales: `$1,234.50`.
 *
 * Punto decimal, como en los comprobantes y en el PDF. Antes la bandeja
 * separaba los miles y las tablas no (`$1234.50`), así que el mismo monto se
 * escribía distinto según la pantalla.
 */
export function dolares(valor?: number | string | null): string {
  const n = Number(valor ?? 0)
  return `$${(Number.isFinite(n) ? n : 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}
