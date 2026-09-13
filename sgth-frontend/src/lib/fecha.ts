/**
 * Conversión de fechas entre el backend y los selectores de Mantine.
 *
 * Vive en `lib/` porque la usan varios módulos. Antes había ocho copias de
 * estas mismas tres funciones: una en `features/sso/utils`, otra en
 * `features/disciplinario/utils` y seis pegadas dentro de componentes de la
 * ficha FEMO, todas ligeramente distintas entre sí.
 *
 * El backend maneja fechas como `YYYY-MM-DD`. Se construyen con el constructor
 * de tres argumentos y no con `new Date(cadena)`, que interpreta esa forma como
 * UTC y en Ecuador (UTC-5) devuelve el día anterior.
 */

/** Fecha del backend a `DD/MM/AAAA`, para mostrar. Sin dato, un guion. */
export function formatFecha(value?: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('es-EC', {
    timeZone: 'UTC',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

/** `YYYY-MM-DD` (o ISO completo) del backend a `Date` local, para el selector. */
export function toDateValue(v?: string | null): Date | null {
  if (!v) return null
  const [y, m, d] = v.substring(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

/**
 * `Date` del selector a `YYYY-MM-DD`. Sin fecha devuelve cadena vacía.
 * Para un campo obligatorio, o cuando el valor va directo a un texto.
 */
export function fromDateValue(d: Date | string | null): string {
  if (!d) return ''
  if (typeof d === 'string') return d.substring(0, 10)
  if (isNaN(d.getTime())) return ''
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

/**
 * Igual que `fromDateValue`, pero devuelve `null` cuando no hay fecha.
 *
 * Para campos opcionales que se limpian: el backend distingue "sin fecha"
 * (`null`) de una cadena vacía, que le llega como fecha inválida.
 */
export function fromDateValueOrNull(d: Date | string | null): string | null {
  return fromDateValue(d) || null
}

/**
 * Fecha y hora del backend a `DD/MM/AAAA HH:MM`, en la hora local.
 *
 * Para columnas `datetime`: el backend las serializa en ISO con zona
 * (`2026-10-20T13:00:00.000000Z`) y `new Date` las lleva a la hora de
 * Ecuador. Viáticos las pasaba antes por `replace(/-/g, '/')`, un truco para
 * el formato `2026-10-20 08:00:00` que con ISO da una fecha inválida: el
 * listado mostraba «Invalid Date» en el período de todos los viáticos.
 */
export function formatFechaHora(
  value?: string | null,
  { conHora = true }: { conHora?: boolean } = {},
): string {
  if (!value) return '—'
  const dt = new Date(value)
  if (isNaN(dt.getTime())) return '—'
  return dt.toLocaleString('es-EC', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    ...(conHora ? { hour: '2-digit', minute: '2-digit' } : {}),
  })
}
