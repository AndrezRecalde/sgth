import type { SemanticTone } from '@/config/design.tokens'
import type { ContenidoPuesto, EstadoFichaManual } from '../services/manualPuestosService'

/**
 * Los estados de una ficha del Manual de Puestos en la revisión (P4, PR 12),
 * en el orden en que se trabajan: primero las listas, al final las cargadas.
 */
export const ESTADOS_FICHA_MANUAL: { value: EstadoFichaManual; label: string; tone: SemanticTone }[] = [
  { value: 'lista',        label: 'Lista',        tone: 'info' },
  { value: 'para_revisar', label: 'Para revisar', tone: 'warning' },
  { value: 'sin_unidad',   label: 'Sin unidad',   tone: 'warning' },
  { value: 'sin_puesto',   label: 'Sin puesto',   tone: 'neutral' },
  { value: 'cargada',      label: 'Cargada',      tone: 'success' },
]

export function estadoFichaManual(estado: EstadoFichaManual) {
  return ESTADOS_FICHA_MANUAL.find((e) => e.value === estado) ?? ESTADOS_FICHA_MANUAL[0]
}

/**
 * «una misión, 3 actividades y 5 competencias»: lo que el puesto ya tiene y la
 * carga reemplaza. Vacío si no tiene nada.
 */
export function textoContenido(c: ContenidoPuesto): string {
  const partes = [
    c.mision ? 'una misión' : null,
    c.requisitos > 0 ? `${c.requisitos} ${c.requisitos === 1 ? 'alternativa de instrucción' : 'alternativas de instrucción'}` : null,
    c.actividades > 0 ? `${c.actividades} ${c.actividades === 1 ? 'actividad' : 'actividades'}` : null,
    c.competencias > 0 ? `${c.competencias} ${c.competencias === 1 ? 'competencia' : 'competencias'}` : null,
  ].filter((p): p is string => p !== null)

  if (partes.length <= 1) return partes.join('')
  return `${partes.slice(0, -1).join(', ')} y ${partes[partes.length - 1]}`
}
