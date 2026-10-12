import type { SemanticTone } from '@/config/design.tokens'
import type {
  EstadoSubrogacion, MotivoSubrogacion, PuestoConRelaciones, TipoAusenciaTitular, TipoSubrogacion,
} from '@/types/api'

/*
| Las etiquetas y los tonos del módulo, en un solo sitio.
|
| Estaban escritas tres veces: `TIPO_OPTIONS`/`MOTIVO_OPTIONS` dentro del modal,
| `TIPO_LABELS`/`MOTIVO_LABELS` dentro del archivo de columnas, y una tercera
| lista en línea en el filtro de la vista. Tres copias del mismo enum del
| backend, que es donde nace la verdad.
*/

export const TIPO_LABELS: Record<TipoSubrogacion, string> = {
  subrogacion: 'Subrogación',
  encargo:     'Encargo',
}

export const MOTIVO_LABELS: Record<MotivoSubrogacion, string> = {
  vacaciones:         'Vacaciones',
  comision_servicios: 'Comisión de Servicios',
  enfermedad:         'Enfermedad',
  licencia:           'Licencia',
  encargo_vacante:    'Encargo por Vacante',
  otro:               'Otro',
}

/** De qué ausencia del titular sale la subrogación (fase 5.2). */
export const AUSENCIA_LABELS: Record<TipoAusenciaTitular, string> = {
  vacacion:        'Vacaciones',
  licencia:        'Licencia con remuneración',
  reposo:          'Reposo médico',
  accion_personal: 'Comisión o licencia por acción de personal',
  otra:            'Otra ausencia, con su resolución',
}

export const ESTADO_LABELS: Record<EstadoSubrogacion, string> = {
  pendiente:  'Pendiente',
  activa:     'Activa',
  finalizada: 'Finalizada',
  cancelada:  'Cancelada',
}

/**
 * El tono sale de lo que el estado significa en el flujo, no de un color
 * elegido por pantalla (regla 06).
 *
 * `pendiente` espera algo de alguien —que su Acción de Personal se registre—,
 * `activa` es lo que terminó bien y surte efecto, `finalizada` ya no cuenta y
 * `cancelada` terminó mal.
 */
export const TONO_SUBROGACION: Record<EstadoSubrogacion, SemanticTone> = {
  pendiente:  'warning',
  activa:     'success',
  finalizada: 'neutral',
  cancelada:  'danger',
}

/** Para un `Select`: el mismo orden en el que los declara el backend. */
export const TIPO_OPTIONS = Object.entries(TIPO_LABELS)
  .map(([value, label]) => ({ value, label }))

/**
 * Si el puesto es del nivel jerárquico superior, el único que se subroga
 * (LOSEP, Art. 126; fase 5.2). La misma regla que `Puesto::
 * esNivelJerarquicoSuperior` en el backend: el grado empieza por «NJS».
 *
 * El motivo ya no se elige: en la subrogación sale de la ausencia del titular,
 * y en el encargo es la vacante. Elegirlo a mano dejaba subrogar «por
 * vacaciones» a un titular que no las tenía.
 */
export function esNivelJerarquicoSuperior(puesto?: PuestoConRelaciones | null): boolean {
  return (puesto?.grupo_ocupacional?.grado_codigo ?? '').toUpperCase().startsWith('NJS')
}
