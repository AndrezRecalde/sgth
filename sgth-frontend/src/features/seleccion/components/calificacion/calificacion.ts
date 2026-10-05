import type { CalificacionItem, CalificacionPostulante, CriterioEvaluacion } from '../../services/criterioService'

/** Lo que el evaluador marca en un criterio: una opción, varias o un número. */
export type ValorCriterio = {
  opcion_id?:      number | null
  opciones_ids?:   number[]
  valor_numerico?: number | null
}

/** Un valor por criterio, con clave `c{id}`: React Hook Form no admite claves numéricas. */
export type CalificacionForm = Record<string, ValorCriterio>

export const claveCriterio = (id: number) => `c${id}`

/** Espeja `CalificacionService` del backend: el checklist suma y no pasa del máximo. */
export function puntajeCriterio(c: CriterioEvaluacion, v: ValorCriterio = {}): number {
  const maximo = Number(c.puntaje_maximo)
  if (c.tipo_input === 'numero') return Math.min(Number(v.valor_numerico ?? 0), maximo)
  if (c.tipo_input === 'radio') return Number(c.opciones.find(o => o.id === v.opcion_id)?.puntaje ?? 0)
  const ids = v.opciones_ids ?? []
  return Math.min(c.opciones.filter(o => ids.includes(o.id)).reduce((s, o) => s + Number(o.puntaje), 0), maximo)
}

/** Las calificaciones guardadas, como valores iniciales del formulario. */
export function valoresIniciales(
  criterios: CriterioEvaluacion[],
  previas: Record<number, CalificacionPostulante> = {},
): CalificacionForm {
  return Object.fromEntries(criterios.map((c) => {
    const p = previas[c.id]
    const valor: ValorCriterio = !p ? {}
      : c.tipo_input === 'numero' ? { valor_numerico: p.valor_numerico != null ? Number(p.valor_numerico) : null }
      : c.tipo_input === 'radio' ? { opcion_id: p.opcion_id ?? null }
      : { opciones_ids: (p.opciones ?? []).map(o => o.id) }
    return [claveCriterio(c.id), valor]
  }))
}

/** Una fila por criterio, como la pide el backend. */
export function aItems(criterios: CriterioEvaluacion[], valores: CalificacionForm): CalificacionItem[] {
  return criterios.map((c) => {
    const v = valores[claveCriterio(c.id)] ?? {}
    return {
      criterio_id:    c.id,
      opcion_id:      c.tipo_input === 'radio' ? v.opcion_id ?? null : null,
      opcion_ids:     c.tipo_input === 'checklist' ? v.opciones_ids ?? [] : undefined,
      valor_numerico: c.tipo_input === 'numero' ? v.valor_numerico ?? null : null,
    }
  })
}
