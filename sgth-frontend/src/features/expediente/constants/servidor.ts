/**
 * Catálogos de la ficha del servidor. Estaban copiados en el formulario y en
 * la pestaña Personal, y ya se habían separado: el orden de los estados
 * civiles no coincidía.
 */
export const GENERO_OPTIONS = [
  { value: 'masculino', label: 'Masculino' },
  { value: 'femenino',  label: 'Femenino' },
  { value: 'otro',      label: 'Otro' },
]

export const ESTADO_CIVIL_OPTIONS = [
  { value: 'soltero',     label: 'Soltero/a' },
  { value: 'casado',      label: 'Casado/a' },
  { value: 'divorciado',  label: 'Divorciado/a' },
  { value: 'viudo',       label: 'Viudo/a' },
  { value: 'union_libre', label: 'Unión libre' },
]

export const TIPO_SANGRE_OPTIONS = [
  'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-',
].map((v) => ({ value: v, label: v }))

const comoEtiquetas = (opciones: { value: string; label: string }[]) =>
  Object.fromEntries(opciones.map((o) => [o.value, o.label]))

export const GENERO_LABELS = comoEtiquetas(GENERO_OPTIONS)
export const ESTADO_CIVIL_LABELS = comoEtiquetas(ESTADO_CIVIL_OPTIONS)
