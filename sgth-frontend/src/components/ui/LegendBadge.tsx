import { Badge, type MantineColor, type MantineSize } from '@mantine/core'

interface Props {
  children: React.ReactNode
  /** El mismo color con el que el gráfico pinta la categoría. */
  color: MantineColor
  size?: MantineSize
}

/**
 * Leyenda de un gráfico: la etiqueta que explica un color que ya está pintado
 * en otro sitio, como la condición de cada pieza en el odontograma.
 *
 * Es la única etiqueta que recibe un color y no un tono, porque aquí el color
 * es el dato: tiene que coincidir con el del dibujo. Para cualquier otra cosa
 * se usa `StatusBadge`.
 */
export function LegendBadge({ children, color, size = 'xs' }: Props) {
  return (
    <Badge variant="light" color={color} size={size}>
      {children}
    </Badge>
  )
}
