import { Card, Group, Stack, Text, ActionIcon, Badge, Box, Tooltip } from '@mantine/core'
import {
  IconArrowsExchange, IconBuilding, IconChevronRight, IconChevronDown,
} from '@tabler/icons-react'
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

function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

export function OrganigramaNode({
  unidad, nivel, expanded, onToggle,
}: OrganigramaNodeProps) {
  const borderColor = NIVEL_COLORS[Math.min(nivel, NIVEL_COLORS.length - 1)]
  const nombre      = unidad.nombre ?? 'Sin nombre'
  const tipoNombre  = unidad.tipo_unidad?.descripcion ?? 'Unidad'
  const puestos     = unidad.puestos_count ?? unidad.puestos?.length ?? 0
  const hijos       = unidad.hijos ?? []
  const hasChildren = hijos.length > 0

  // El organigrama es la estructura formal; esto es quién la está ejerciendo
  // hoy. Sin la marca, un jefe subrogante es invisible aquí — y es justamente
  // a quien hay que ubicar cuando se busca al responsable de la unidad.
  const subrogaciones = unidad.subrogaciones_vigentes ?? []

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
          <Stack gap={4}>
            <Text fw={600} size="sm">{nombre}</Text>
            <Text size="xs" c="dimmed">
              {tipoNombre}
              {puestos > 0 && ` · ${puestos} ${puestos === 1 ? 'puesto' : 'puestos'}`}
            </Text>
            {subrogaciones.length > 0 && (
              <Group gap={4}>
                {subrogaciones.map((s) => (
                  <Tooltip
                    key={s.id}
                    withArrow
                    multiline
                    w={260}
                    label={
                      `${s.subrogante ?? 'Sin nombre'} — ${s.puesto ?? 'puesto sin cargo'}`
                      + (s.fecha_fin ? ` · hasta ${formatFecha(s.fecha_fin)}` : '')
                    }
                  >
                    <Badge
                      size="xs"
                      variant="light"
                      color={s.tipo === 'encargo' ? 'blue' : 'grape'}
                      leftSection={<IconArrowsExchange size={11} />}
                    >
                      {s.tipo === 'encargo' ? 'Encargo' : 'Subrogación'}
                    </Badge>
                  </Tooltip>
                ))}
              </Group>
            )}
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
