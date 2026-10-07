import type { PermisoServidor } from '@/types/api'

/*
| Por qué un permiso está como está.
|
| Rechazar, anular y revertir exigen motivo y el backend lo guarda, pero
| ninguna pantalla lo enseñaba: el servidor veía «Rechazado» sin saber qué
| corregir, y Talento Humano tenía que preguntar quién anuló y por qué.
|
| La reversión solo se muestra mientras el permiso sigue pendiente: si después
| se confirma otra vez, el motivo quedó atrás.
*/

export interface MotivoDeEstado {
  /** Para la ficha: «Motivo del rechazo». */
  etiqueta: string
  /** Para la tabla, debajo de la etiqueta de estado. */
  enTabla:  string
  motivo:   string
}

export function motivoDeEstado(p: PermisoServidor): MotivoDeEstado | null {
  if (p.estado === 'rechazado' && p.motivo_rechazo) {
    return { etiqueta: 'Motivo del rechazo', enTabla: p.motivo_rechazo, motivo: p.motivo_rechazo }
  }

  if (p.estado === 'anulado' && p.motivo_anulacion) {
    return { etiqueta: 'Motivo de la anulación', enTabla: p.motivo_anulacion, motivo: p.motivo_anulacion }
  }

  if (p.estado === 'pendiente' && p.motivo_reversion) {
    return {
      etiqueta: 'Confirmación revertida',
      enTabla:  `Confirmación revertida: ${p.motivo_reversion}`,
      motivo:   p.motivo_reversion,
    }
  }

  return null
}
