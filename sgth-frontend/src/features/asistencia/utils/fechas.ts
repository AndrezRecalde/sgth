/**
 * Las fechas `YYYY-MM-DD` del API, en hora local.
 *
 * `new Date('2026-09-11')` las lee como medianoche UTC, que en Ecuador es el
 * día anterior. Por eso se arman con año, mes y día.
 */

export const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('-').map(Number)

  return new Date(y, m - 1, d)
}

export const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  if (typeof d === 'string') return d.substring(0, 10)

  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/**
 * Días calendario de un rango, contando los dos extremos: del 1 al 3 son 3.
 *
 * `null` si falta una de las fechas o no es válida, y 0 si el fin es anterior
 * al inicio.
 */
export function diasCalendario(inicio: string, fin: string): number | null {
  const desde = toDate(inicio)
  const hasta = toDate(fin)

  if (!desde || !hasta || isNaN(desde.getTime()) || isNaN(hasta.getTime())) return null
  if (hasta < desde) return 0

  return Math.round((hasta.getTime() - desde.getTime()) / 86_400_000) + 1
}
