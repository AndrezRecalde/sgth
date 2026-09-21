import type { SemanticTone } from '@/config/design.tokens'
import type { ServidorConRelaciones } from '@/types/api'

/**
 * En qué situación está la persona, en una sola frase.
 *
 * Antes la tabla mostraba «Activo» leyendo la columna `estado` de la ficha, y
 * decía activo incluso a quien nunca llegó a tener un vínculo.
 */
export function situacionDe(
  servidor: ServidorConRelaciones,
): { texto: string; tone: SemanticTone } {
  if (!servidor.estado) return { texto: 'Inactivo', tone: 'neutral' }
  if (servidor.pendiente_vinculacion) return { texto: 'Sin vínculo', tone: 'warning' }
  return { texto: 'En funciones', tone: 'success' }
}
