import type { SemanticTone } from '@/config/design.tokens'
import type { EstadoBaja, MotivoBaja } from '../services/tiposBaja'

/** Los motivos de `App\Enums\MotivoBaja`. */
export const MOTIVOS_BAJA: { value: MotivoBaja; label: string }[] = [
  { value: 'dano_irreparable', label: 'Daño irreparable' },
  { value: 'obsolescencia',    label: 'Obsolescencia' },
  { value: 'robo_o_perdida',   label: 'Robo o pérdida' },
  { value: 'siniestro',        label: 'Siniestro' },
  { value: 'otro',             label: 'Otro' },
]

/** Propuesta: espera al jefe; aprobada: el equipo salió; rechazada: sigue en servicio. */
export const TONO_ESTADO_BAJA: Record<EstadoBaja, SemanticTone> = {
  propuesta: 'warning',
  aprobada:  'neutral',
  rechazada: 'info',
}

export const ESTADOS_BAJA: { value: EstadoBaja; label: string }[] = [
  { value: 'propuesta', label: 'Propuestas' },
  { value: 'aprobada',  label: 'Aprobadas' },
  { value: 'rechazada', label: 'Rechazadas' },
]
