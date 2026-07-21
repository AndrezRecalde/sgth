/** Formatea una fecha (date o datetime ISO del backend) como DD/MM/AAAA para mostrar en tablas. */
export function formatFecha(value?: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('es-EC', {
    timeZone: 'UTC',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

/** Convierte un string 'YYYY-MM-DD' (o datetime ISO) del backend a Date local, para DatePickerInput. */
export function toDateValue(v?: string | null): Date | null {
  if (!v) return null
  const [y, m, d] = v.substring(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

/** Convierte el Date seleccionado en DatePickerInput a 'YYYY-MM-DD' para enviar al backend. */
export function fromDateValue(d: Date | string | null): string {
  if (!d) return ''
  if (typeof d === 'string') return d.substring(0, 10)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
