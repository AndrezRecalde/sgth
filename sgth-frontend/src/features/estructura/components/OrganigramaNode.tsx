import { Card, Group, Stack, Text, ActionIcon, Box } from '@mantine/core'
import { IconBuilding, IconChevronRight, IconChevronDown } from '@tabler/icons-react'
import type { UnidadAdministrativa } from '@/types/api'

interface OrganigramaNodeProps {
  unidad: UnidadAdministrativa
  nivel: number
  expanded: boolean
  onToggle: (id: string) => void
}

export function OrganigramaNode({ unidad, nivel, expanded, onToggle }: OrganigramaNodeProps) {
  const getBorderColor = (nivel: number) => {
    if (nivel === 0) return 'var(--mantine-color-emerald-6)'
    if (nivel === 1) return 'var(--mantine-color-emerald-4)'
    if (nivel === 2) return 'var(--mantine-color-emerald-2)'
    return 'var(--mantine-color-gray-3)'
  }

  // Resolviendo nombres de propiedades según OpenAPI
  const nombre = (unidad as any).nombre || 'Sin nombre'
  const tipoUnidad = (unidad as any).tipo_unidad?.nombre || 'Unidad'
  const puestosCount = (unidad as any).puestos_count ?? (unidad as any).puestos?.length ?? 0
  
  const hijos = (unidad as any).hijas || (unidad as any).unidades_hijas || (unidad as any).children || []
  const hasChildren = hijos.length > 0

  return (
    <Card 
      shadow="sm" 
      padding="sm" 
      radius="md" 
      withBorder 
      style={{ 
        borderLeft: `4px solid ${getBorderColor(nivel)}` 
      }}
    >
      <Group justify="space-between" wrap="nowrap">
        <Group wrap="nowrap">
          <Box c="dimmed">
            <IconBuilding size={24} />
          </Box>
          <Stack gap={0}>
            <Text fw={600} size="sm">
              {nombre}
            </Text>
            <Text size="xs" c="dimmed">
              {tipoUnidad} • {puestosCount} {puestosCount === 1 ? 'puesto' : 'puestos'}
            </Text>
          </Stack>
        </Group>

        {hasChildren && (
          <ActionIcon 
            variant="subtle" 
            color="gray" 
            onClick={() => onToggle(unidad.id)}
          >
            {expanded ? <IconChevronDown size={18} /> : <IconChevronRight size={18} />}
          </ActionIcon>
        )}
      </Group>
    </Card>
  )
}
