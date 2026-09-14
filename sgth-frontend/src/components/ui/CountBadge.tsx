import { Badge, type BadgeProps } from '@mantine/core'
import { SEMANTIC_COLOR, type SemanticTone } from '@/config/design.tokens'

type Props = Omit<BadgeProps, 'color' | 'variant' | 'circle' | 'gradient'> & {
  /** Solo si el número es bueno o malo: 3 observados es `danger`, 3 entregas no. */
  tone?: SemanticTone
  /** Relleno, para el contador que pide acción (la bandeja con pendientes). */
  destacado?: boolean
}

/**
 * Un número suelto dentro de una pastilla: cuántos permisos, cuántos ítems,
 * cuántos pendientes en una bandeja.
 *
 * Si el número va acompañado de texto («3 en espera», «45 pts») es una
 * etiqueta y va en `StatusBadge`. Aquí va solo la cifra.
 */
export function CountBadge({ tone = 'neutral', destacado = false, size = 'sm', ...props }: Props) {
  return (
    <Badge
      {...props}
      circle
      variant={destacado ? 'filled' : 'light'}
      color={SEMANTIC_COLOR[tone]}
      size={size}
    />
  )
}
