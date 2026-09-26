import { Grid, Stack, Text } from '@mantine/core'

export interface DetailItem {
  label: string
  value: React.ReactNode
  /** Ocupa la fila completa. Para observaciones y textos largos. */
  ancho?: boolean
}

interface Props {
  items: DetailItem[]
  /**
   * Columnas en escritorio. En móvil siempre es una.
   *
   * `1` es para un panel estrecho —una columna de un cajón de detalle, una
   * barra lateral—, donde dos pares por fila se pisan.
   */
  columnas?: 1 | 2 | 3
}

/**
 * Pares etiqueta/valor de una vista de detalle (expediente, ficha FEMO,
 * detalle de viático).
 *
 * La etiqueta va arriba, pequeña y atenuada; el valor debajo con el peso.
 * Poner la etiqueta a la izquierda obligaría a alinear dos columnas de ancho
 * variable y rompe en cuanto una etiqueta es larga.
 *
 * Un valor ausente se dibuja como guion y no como hueco: así se distingue
 * "no tiene dato" de "se me olvidó pintarlo".
 */
export function DetailList({ items, columnas = 2 }: Props) {
  const span = { 1: 12, 2: 6, 3: 4 }[columnas]

  return (
    <Grid gap="md">
      {items.map((item) => (
        <Grid.Col
          key={item.label}
          span={{ base: 12, sm: item.ancho ? 12 : span }}
        >
          <Stack gap={2}>
            <Text size="xs" c="dimmed" fw={600}>
              {item.label}
            </Text>
            {/* `div` y no el `p` por defecto: el valor puede ser una etiqueta, un
                botón u otro Text, y un bloque dentro de un párrafo es HTML
                inválido que React marca como error de hidratación. */}
            <Text size="sm" component="div">
              {item.value === null || item.value === undefined || item.value === ''
                ? '—'
                : item.value}
            </Text>
          </Stack>
        </Grid.Col>
      ))}
    </Grid>
  )
}
