import type { SemanticTone } from '@/config/design.tokens'
import type {
  EstadoSubrogacion, MotivoSubrogacion, TipoSubrogacion,
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

export const MOTIVO_OPTIONS = Object.entries(MOTIVO_LABELS)
  .map(([value, label]) => ({ value, label }))

/**
 * El motivo que corresponde a cada figura.
 *
 * Un encargo recae sobre un puesto vacante, así que su motivo es el encargo por
 * vacante; el formulario arrancaba en «Vacaciones» y se quedaba ahí al cambiar
 * de figura, de modo que un encargo salía motivado por las vacaciones de un
 * titular que no existe.
 */
export const MOTIVO_POR_DEFECTO: Record<TipoSubrogacion, MotivoSubrogacion> = {
  subrogacion: 'vacaciones',
  encargo:     'encargo_vacante',
}

/**
 * Los motivos que tienen sentido en cada figura. «Encargo por vacante» no
 * explica una subrogación —ahí hay titular—, y los motivos de ausencia del
 * titular no explican un encargo.
 */
export function motivosPara(tipo: TipoSubrogacion) {
  return tipo === 'encargo'
    ? MOTIVO_OPTIONS.filter((o) => o.value === 'encargo_vacante' || o.value === 'otro')
    : MOTIVO_OPTIONS.filter((o) => o.value !== 'encargo_vacante')
}
