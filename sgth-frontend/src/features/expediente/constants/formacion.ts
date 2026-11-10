import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoFormacion, NivelEstudio } from '@/types/api'

/** `EstadoFormacion` del backend: lo que registra el servidor espera a TH. */
export const ESTADO_FORMACION_LABELS: Record<EstadoFormacion, string> = {
  validado:    'Validado',
  por_validar: 'Por validar',
  rechazado:   'Rechazado',
}

export const TONO_ESTADO_FORMACION: Record<EstadoFormacion, SemanticTone> = {
  validado:    'success',
  por_validar: 'warning',
  rechazado:   'danger',
}

/**
 * `NivelEstudio` del backend, de menor a mayor, con sus etiquetas. Estaban
 * escritas en tres sitios, y el mismo título salía «Tercer nivel (Pregrado)» en
 * la ficha y «Tercer nivel» en la bandeja de Talento Humano.
 *
 * El técnico superior y el tecnológico superior entraron el 2026-11-10
 * (docs/diseno-manual-de-puestos.md, P2): unas 60 fichas del Manual de Puestos
 * los piden. Son tercer nivel técnico-tecnológico (LOES, Art. 118), por eso el
 * de las carreras universitarias se llama «de grado».
 */
export const NIVELES_ESTUDIO: { value: NivelEstudio; label: string }[] = [
  { value: 'primaria',             label: 'Primaria' },
  { value: 'secundaria',           label: 'Secundaria' },
  { value: 'tecnico_superior',     label: 'Técnico superior' },
  { value: 'tecnologico_superior', label: 'Tecnológico superior' },
  { value: 'tercer_nivel',         label: 'Tercer nivel de grado' },
  { value: 'cuarto_nivel',         label: 'Cuarto nivel (posgrado)' },
]

export function etiquetaNivelEstudio(nivel?: string | null): string | undefined {
  return NIVELES_ESTUDIO.find((n) => n.value === nivel)?.label
}
