import { Stack, Text, ThemeIcon } from '@mantine/core'
import type { Icon } from '@tabler/icons-react'

interface Props {
  icon: Icon
  title: string
  description?: string
  /** Acción que resuelve el vacío: normalmente "crear el primero". */
  action?: React.ReactNode
}

/**
 * Estado vacío de un listado.
 *
 * Un vacío siempre dice DOS cosas: qué falta y qué hacer al respecto. Si la
 * pantalla permite crear el registro, `action` no es opcional en la práctica.
 *
 * Para "sin resultados de búsqueda" se usa el mismo componente cambiando el
 * texto; para una lista pequeña vacía dentro de una tarjeta basta un
 * `<Text size="sm" c="dimmed">`, sin montar todo esto.
 */
export function EmptyState({ icon: Icono, title, description, action }: Props) {
  return (
    <Stack align="center" gap="md" py={48}>
      <ThemeIcon size={52} radius="xl" variant="light" color="gray">
        <Icono size={26} stroke={1.6} />
      </ThemeIcon>

      <Stack align="center" gap={4}>
        <Text fw={600} c="dimmed">
          {title}
        </Text>
        {description && (
          <Text size="sm" c="dimmed" ta="center" maw={380}>
            {description}
          </Text>
        )}
      </Stack>

      {action}
    </Stack>
  )
}
