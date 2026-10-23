import type { ItemOrdenExamen, OrdenExamen } from '../services/examenService'

export const ordenAbierta = (o: OrdenExamen) => o.estado === 'emitida' || o.estado === 'parcial'

/** Los que todavía se esperan: lo que un resultado nuevo cubre por defecto. */
export const pendientesDe = (o: OrdenExamen) =>
  o.items.filter((i) => i.estado === 'pendiente').map((i) => i.id)

/**
 * El tipo de resultado que corresponde a lo marcado. El servidor lo pide
 * siempre; con orden se propone a partir del área de los exámenes.
 */
export function tipoSugerido(items: ItemOrdenExamen[]): string {
  const areas = items.map((i) => i.examen?.area).filter(Boolean)
  if (!areas.length) return ''
  if (areas.every((a) => a === 'radiografias')) return 'rayos_x'
  if (areas.every((a) => a === 'ecografias')) return 'ecografia'
  if (areas.every((a) => a === 'ocupacionales')) return 'otro'
  return 'laboratorio'
}

/** La descripción que se propone: los exámenes que cubre el archivo. */
export const descripcionSugerida = (items: ItemOrdenExamen[]) =>
  items.map((i) => i.examen?.nombre).filter(Boolean).join(', ')
