import { toDateValue } from '@/lib/fecha'

/**
 * Cuándo caduca un lote, con la misma regla que el resto del sistema.
 *
 * **El día impreso en el envase todavía es válido.** Así lo decide el backend
 * —`fecha_caducidad->startOfDay()->isBefore(now()->startOfDay())`— y así lo
 * decidía `DarDeBajaStockModal`; la columna de la tabla de inventario no, y de
 * ahí que un lote que caducaba hoy saliera en rojo como «Vencido» mientras el
 * despacho seguía entregándolo.
 *
 * El fallo estaba en comparar la fecha de caducidad, que llega a medianoche,
 * contra `new Date()`, que lleva la hora actual: por la tarde la resta daba
 * −0,6 días y `Math.floor` la bajaba a −1. Aquí los dos extremos se ponen a
 * medianoche antes de restar.
 */
function hoyAMedianoche(): Date {
  const hoy = new Date()
  hoy.setHours(0, 0, 0, 0)
  return hoy
}

/**
 * Días que faltan para que caduque. 0 es «caduca hoy», negativo es que ya
 * pasó. `null` si no hay fecha o no es válida.
 *
 * `Math.round` y no `Math.floor`: con los dos extremos a medianoche la resta
 * ya es un múltiplo exacto de un día, y redondear protege de un cambio de
 * hora que dejara la diferencia en 23 o 25 horas.
 */
export function diasParaCaducar(fecha?: string | null): number | null {
  const caduca = toDateValue(fecha)
  if (!caduca || isNaN(caduca.getTime())) return null

  return Math.round(
    (caduca.getTime() - hoyAMedianoche().getTime()) / 86_400_000
  )
}

/** ¿Ya pasó el día impreso en el envase? El propio día todavía no cuenta. */
export function estaCaducado(fecha?: string | null): boolean {
  const dias = diasParaCaducar(fecha)
  return dias !== null && dias < 0
}
