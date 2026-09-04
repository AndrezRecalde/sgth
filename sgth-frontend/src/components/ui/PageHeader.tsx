import Link from 'next/link'
import { ActionIcon, Group, Stack, Text, Title, Tooltip } from '@mantine/core'
import { IconArrowLeft } from '@tabler/icons-react'
import classes from './PageHeader.module.css'

interface Props {
  title: string
  /** Una línea que explica para qué sirve la pantalla. Opcional. */
  description?: string
  /** Acciones principales de la pantalla, alineadas a la derecha. */
  actions?: React.ReactNode
  /** Muestra una flecha de retorno. Solo en pantallas de detalle. */
  backHref?: string
  backLabel?: string
}

/**
 * Cabecera de página: qué es esta pantalla y qué puedo hacer en ella.
 *
 * No lleva icono decorativo: la ubicación ya la comunican el menú lateral y
 * las migas de pan de la barra superior, y un icono grande junto al título
 * solo repite esa información ocupando espacio vertical.
 *
 * Tampoco lleva separador inferior: el aire de `PageShell` ya separa la
 * cabecera del contenido.
 *
 * Cuando el título y las acciones no caben en una fila —un teléfono, o una
 * cabecera con varios botones— las acciones bajan a la suya. El reparto vive
 * en `PageHeader.module.css`.
 */
export function PageHeader({
  title,
  description,
  actions,
  backHref,
  backLabel = 'Volver',
}: Props) {
  return (
    <Group justify="space-between" align="flex-start" wrap="wrap" gap="md">
      <Group
        gap="sm"
        wrap="nowrap"
        align="center"
        className={classes.identidad}
      >
        {backHref && (
          <Tooltip label={backLabel}>
            <ActionIcon
              component={Link}
              href={backHref}
              size="lg"
              radius="md"
              aria-label={backLabel}
            >
              <IconArrowLeft size={19} stroke={1.6} />
            </ActionIcon>
          </Tooltip>
        )}

        <Stack gap={2} style={{ minWidth: 0 }}>
          <Title order={1}>{title}</Title>
          {description && (
            <Text size="sm" c="dimmed">
              {description}
            </Text>
          )}
        </Stack>
      </Group>

      {actions && (
        <Group gap="sm" wrap="wrap" style={{ flexShrink: 0 }}>
          {actions}
        </Group>
      )}
    </Group>
  )
}
