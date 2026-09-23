import { toDateValue } from '@/lib/fecha'

/**
 * Lo que Asistencia necesita de las fechas y no está en `lib/fecha`.
 *
 * Aquí vivían también `toDate` y `fromDate`, que duplicaban `toDateValue` y
 * `fromDateValueOrNull` de la librería. Se fueron: su `fromDate` no comprobaba
 * `isNaN`, así que ante un `Date` inválido enviaba la cadena `"NaN-NaN-NaN"`
 * al backend, y su `toDate` partía por `-` sin recortar, de modo que una fecha
 * con hora (`2026-09-11T00:00:00Z`) daba `Invalid Date`.
 */

/**
 * Días calendario de un rango, contando los dos extremos: del 1 al 3 son 3.
 *
 * `null` si falta una de las fechas o no es válida, y 0 si el fin es anterior
 * al inicio.
 */
export function diasCalendario(inicio: string, fin: string): number | null {
  const desde = toDateValue(inicio)
  const hasta = toDateValue(fin)

  if (!desde || !hasta || isNaN(desde.getTime()) || isNaN(hasta.getTime())) return null
  if (hasta < desde) return 0

  return Math.round((hasta.getTime() - desde.getTime()) / 86_400_000) + 1
}
