import { Card, Group, Stack, Text, Title } from '@mantine/core'

interface Props {
  title: string
  description?: string
  /** Acciones de la sección, alineadas con el título. */
  actions?: React.ReactNode
  children: React.ReactNode
}

/**
 * Bloque con título dentro de una página.
 *
 * Sustituye al patrón de `<Divider label={...}>` que se repetía para separar
 * secciones: una tarjeta delimita el contenido de verdad, mientras que un
 * divisor solo dibuja una línea y deja la agrupación a la interpretación.
 */
export function SectionCard({ title, description, actions, children }: Props) {
  return (
    <Card withBorder radius="lg" padding="lg">
      <Group justify="space-between" align="flex-start" wrap="nowrap" mb="md" gap="md">
        <Stack gap={2} style={{ minWidth: 0 }}>
          <Title order={3}>{title}</Title>
          {description && (
            <Text size="sm" c="dimmed">
              {description}
            </Text>
          )}
        </Stack>
        {actions && (
          <Group gap="xs" wrap="nowrap" style={{ flexShrink: 0 }}>
            {actions}
          </Group>
        )}
      </Group>

      {children}
    </Card>
  )
}
