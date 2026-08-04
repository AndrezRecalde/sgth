// Refleja el enum TipoNombramiento del backend (App\Enums\TipoNombramiento).
// Compartido por los formularios que crean un vínculo y por las vistas que solo
// muestran el nombramiento, para no duplicar la lista ni acabar imprimiendo el
// valor crudo del enum.
export const TIPO_NOMBRAMIENTO_OPTIONS = [
  {
    value: 'nombramiento_permanente',
    label: 'Nombramiento Permanente',
  },
  {
    value: 'nombramiento_provisional',
    label: 'Nombramiento Provisional',
  },
  {
    value: 'servicios_ocasionales',
    label: 'Contrato de Servicios Ocasionales',
  },
  {
    value: 'libre_nombramiento_remocion',
    label: 'Libre Nombramiento y Remoción',
  },
  {
    value: 'codigo_trabajo',
    label: 'Código del Trabajo',
  },
  {
    value: 'servicios_profesionales',
    label: 'Servicios Profesionales',
  },
  {
    value: 'eleccion_popular',
    label: 'Elección Popular',
  },
]

export const TIPO_NOMBRAMIENTO_LABELS: Record<string, string> = Object.fromEntries(
  TIPO_NOMBRAMIENTO_OPTIONS.map((o) => [o.value, o.label]),
)

/** Devuelve el valor tal cual si es un nombramiento que aún no está mapeado. */
export function etiquetaNombramiento(valor?: string | null): string | null {
  if (!valor) return null
  return TIPO_NOMBRAMIENTO_LABELS[valor] ?? valor
}
