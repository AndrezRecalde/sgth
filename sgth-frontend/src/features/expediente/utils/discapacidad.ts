/**
 * Etiquetas de `TipoDiscapacidad` del backend. Vivían copiadas dentro de la
 * pestaña Familia, y la pestaña Condición mostraba el valor crudo («fisica»).
 */
export const TIPO_DISCAPACIDAD_LABELS: Record<string, string> = {
  fisica: 'Física',
  sensorial: 'Sensorial',
  intelectual: 'Intelectual',
  psicosocial: 'Psicosocial',
  visceral: 'Visceral',
  multiple: 'Múltiple',
}

/** Las opciones del selector, con la descripción larga de cada tipo. */
export const TIPO_DISCAPACIDAD_OPTIONS = [
  { value: 'fisica',      label: 'Física' },
  { value: 'sensorial',   label: 'Sensorial (Visual / Auditiva)' },
  { value: 'intelectual', label: 'Intelectual' },
  { value: 'psicosocial', label: 'Psicosocial o Mental' },
  { value: 'visceral',    label: 'Visceral u Orgánica' },
  { value: 'multiple',    label: 'Múltiple' },
]

/** Por debajo de este porcentaje no se registra una discapacidad. */
export const PORCENTAJE_MINIMO_DISCAPACIDAD = 5

/**
 * El grado según el porcentaje, con los rangos que fijó Talento Humano el
 * 2026-10-03. Espejo de `App\Enums\GradoDiscapacidad`; no se guarda, se
 * deriva, así porcentaje y grado no pueden contradecirse.
 */
export function gradoDiscapacidad(porcentaje?: number | string | null): string | null {
  if (porcentaje == null || porcentaje === '') return null
  const valor = Number(porcentaje)
  if (Number.isNaN(valor) || valor < PORCENTAJE_MINIMO_DISCAPACIDAD) return null
  if (valor < 25) return 'Leve'
  if (valor < 50) return 'Moderada'
  if (valor < 75) return 'Grave'
  return 'Muy grave o completa'
}
