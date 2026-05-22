import { Card, Group, Stack, Text, ActionIcon, Box } from '@mantine/core'
import { IconBuilding, IconChevronRight, IconChevronDown } from '@tabler/icons-react'
import type { UnidadConRelaciones } from '@/types/api'

interface OrganigramaNodeProps {
  unidad: UnidadConRelaciones
  nivel: number
  expanded: boolean
  onToggle: (id: number) => void
}

const NIVEL_COLORS = [
  'var(--mantine-color-emerald-6)',
  'var(--mantine-color-emerald-4)',
  'var(--mantine-color-emerald-2)',
  'var(--mantine-color-gray-3)',
]

export function OrganigramaNode({
  unidad, nivel, expanded, onToggle,
}: OrganigramaNodeProps) {
  const borderColor = NIVEL_COLORS[Math.min(nivel, NIVEL_COLORS.length - 1)]
  const nombre      = unidad.nombre ?? 'Sin nombre'
  const tipoNombre  = unidad.tipo_unidad?.descripcion ?? 'Unidad'
  const puestos     = unidad.puestos_count ?? unidad.puestos?.length ?? 0
  const hijos       = unidad.hijos ?? []
  const hasChildren = hijos.length > 0

  return (
    <Card
      shadow="none"
      padding="sm"
      radius="md"
      withBorder
      style={{ borderLeft: `4px solid ${borderColor}` }}
    >
      <Group justify="space-between" wrap="nowrap">
        <Group wrap="nowrap">
          <Box c="dimmed">
            <IconBuilding size={20} />
          </Box>
          <Stack gap={0}>
            <Text fw={600} size="sm">{nombre}</Text>
            <Text size="xs" c="dimmed">
              {tipoNombre}
              {puestos > 0 && ` · ${puestos} ${puestos === 1 ? 'puesto' : 'puestos'}`}
            </Text>
          </Stack>
        </Group>
        {hasChildren && (
          <ActionIcon
            variant="subtle"
            color="gray"
            onClick={() => onToggle(Number(unidad.id))}
            aria-label={expanded ? 'Colapsar' : 'Expandir'}
          >
            {expanded
              ? <IconChevronDown size={16} />
              : <IconChevronRight size={16} />}
          </ActionIcon>
        )}
      </Group>
    </Card>
  )
}
