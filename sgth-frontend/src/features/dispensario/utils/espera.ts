import { fromDateValue, hoyIso } from '@/lib/fecha'

/**
 * Días entre una fecha y hoy, en la hora local: lo que lleva esperando una
 * orden o un resultado. Una fecha con hora —`created_at` llega en UTC— se lleva
 * antes a su día local: a las 19:00 en Ecuador ya es el día siguiente en UTC.
 */
export function diasDesde(fecha: string): number {
  const dia = fecha.length > 10 ? fromDateValue(new Date(fecha)) : fecha
  return Math.round((Date.parse(`${hoyIso()}T00:00:00Z`) - Date.parse(`${dia}T00:00:00Z`)) / 86_400_000)
}

export const textoEspera = (dias: number) => (dias === 0 ? 'Hoy' : `${dias} ${dias === 1 ? 'día' : 'días'}`)
