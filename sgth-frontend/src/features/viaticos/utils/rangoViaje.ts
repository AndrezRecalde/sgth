import { formatFechaHora } from '@/lib/fecha'

type ConFechas = { datetime_salida?: unknown; datetime_llegada?: unknown }

/**
 * Los días del viaje, para acotar los calendarios de actividades y
 * comprobantes: fuera de ellos Gestión Financiera rechaza el comprobante.
 *
 * Desde el inicio del día de salida hasta el final del de regreso, en la
 * hora local. Los dos modales lo calculaban por su cuenta, y el de
 * actividades mostraba el regreso en UTC: un día de más si era de noche.
 */
export function rangoDelViaje(v: ConFechas) {
  const salida = typeof v.datetime_salida === 'string' ? v.datetime_salida : null
  const llegada = typeof v.datetime_llegada === 'string' ? v.datetime_llegada : null

  const min = salida ? new Date(salida) : undefined
  min?.setHours(0, 0, 0, 0)
  const max = llegada ? new Date(llegada) : undefined
  max?.setHours(23, 59, 59, 999)

  return {
    min,
    max,
    desde: formatFechaHora(salida, { conHora: false }),
    hasta: formatFechaHora(llegada, { conHora: false }),
  }
}
