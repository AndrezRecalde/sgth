import type { SemanticTone } from '@/config/design.tokens'

/**
 * El estado real de la ventana de una campaña de tamizaje, tal como lo
 * calcula el backend (`App\Enums\EstadoCampaniaSso`).
 *
 * No es la columna `activa`, que solo dice si alguien la cerró a mano. Las
 * dos pantallas de campañas pintaban `activa ? 'Abierta' : 'Cerrada'`, y eso
 * mentía de dos formas: una campaña con la apertura el mes que viene salía
 * «Abierta», y una cuya fecha de cierre ya pasó también — mientras el enlace
 * público rechazaba a quien entraba.
 */
export type EstadoCampaniaSso = 'programada' | 'abierta' | 'cerrada'

export const ESTADO_CAMPANIA_LABELS: Record<EstadoCampaniaSso, string> = {
  programada: 'Programada',
  abierta: 'Abierta',
  cerrada: 'Cerrada',
}

/**
 * Los tonos, y por qué cada uno.
 *
 * `abierta` → `success`: la regla 03 pone «vigente» en esa lista, y una
 * campaña abierta es justo eso.
 *
 * `cerrada` → `neutral`: «cerrado» está literalmente en la lista de neutral.
 * No es un fallo, es el final normal de una campaña.
 *
 * `programada` → `warning`: de los cinco tonos es el único que significa «esto
 * espera algo», y aquí espera su propia fecha de apertura. El motivo de no
 * dejarla en `neutral`, que por significado también encajaría, es que entonces
 * se vería igual que `cerrada`, y la diferencia entre las dos es justo la que
 * la pantalla tiene que comunicar: si el enlace ya se puede repartir o no.
 */
export const TONO_ESTADO_CAMPANIA: Record<EstadoCampaniaSso, SemanticTone> = {
  programada: 'warning',
  abierta: 'success',
  cerrada: 'neutral',
}

/** Lo que se le puede decir a quien mira la fila, en una línea. */
export const AYUDA_ESTADO_CAMPANIA: Record<EstadoCampaniaSso, string> = {
  programada: 'Todavía no admite respuestas: su fecha de apertura es futura.',
  abierta: 'Admite respuestas. El enlace público ya se puede repartir.',
  cerrada: 'No admite respuestas: se cerró a mano o su fecha de cierre ya pasó.',
}
