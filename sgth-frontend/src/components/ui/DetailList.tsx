import { Grid, Stack, Text } from '@mantine/core'

export interface DetailItem {
  label: string
  value: React.ReactNode
  /** Ocupa la fila completa. Para observaciones y textos largos. */
  ancho?: boolean
}

interface Props {
  items: DetailItem[]
  /** Columnas en escritorio. En móvil siempre es una. */
  columnas?: 2 | 3
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
  const span = columnas === 3 ? 4 : 6

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
            <Text size="sm">
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
