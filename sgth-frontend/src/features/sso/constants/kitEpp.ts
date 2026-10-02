import type { SemanticTone } from '@/config/design.tokens'

/**
 * En qué punto está un equipo del kit que el puesto requiere, tal como lo
 * calcula el backend (`App\Enums\EstadoKitEpp`).
 *
 * El modal «Entregar kit completo» premarcaba todo el kit del puesto,
 * siempre, porque el endpoint no sabía nada de lo ya entregado: entregarlo
 * dos veces creaba filas duplicadas en la bitácora sin un aviso.
 */
export type EstadoKitEpp = 'pendiente' | 'por_reponer' | 'vigente'

export const ESTADO_KIT_LABELS: Record<EstadoKitEpp, string> = {
  pendiente: 'Pendiente',
  por_reponer: 'Por reponer',
  vigente: 'Vigente',
}

/**
 * `pendiente` → `warning`: falta entregarlo, y eso espera algo de alguien.
 * `por_reponer` → `danger`: el plazo ya se cumplió, así que el servidor está
 * usando un equipo vencido; es lo que de verdad hay que mirar de la lista.
 * `vigente` → `success`, que es donde la regla 03 pone «vigente».
 */
export const TONO_ESTADO_KIT: Record<EstadoKitEpp, SemanticTone> = {
  pendiente: 'warning',
  por_reponer: 'danger',
  vigente: 'success',
}

/** Lo que se premarca al abrir el modal: lo que falta y lo que ya toca. */
export function tocaEntregar(estado: EstadoKitEpp | undefined): boolean {
  return estado !== 'vigente'
}
