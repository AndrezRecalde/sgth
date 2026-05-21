import { Text, Badge, Group, ActionIcon } from '@mantine/core'
import { IconEye, IconEdit } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import type { ServidorConRelaciones, EstadoContrato } from '@/types/api'

const ESTADO_COLORS: Record<EstadoContrato, string> = {
  vigente:   'emerald',
  terminado: 'gray',
  cancelado: 'red',
}

const ESTADO_LABELS: Record<EstadoContrato, string> = {
  vigente:   'Vigente',
  terminado: 'Terminado',
  cancelado: 'Cancelado',
}

type Handlers = {
  onView: (servidor: ServidorConRelaciones) => void
  onEdit: (servidor: ServidorConRelaciones) => void
}

export const getServidorColumns = (
  { onView, onEdit }: Handlers
): DataTableColumn<ServidorConRelaciones>[] => [
  {
    accessor: 'cedula',
    title: 'Cédula',
    width: 110,
    render: ({ cedula }) => (
      <Text size="sm" ff="monospace">{cedula ?? '-'}</Text>
    ),
  },
  {
    accessor: 'nombres',
    title: 'Nombre completo',
    render: (row) => (
      <Text size="sm" fw={500}>
        {`${row.apellidos ?? ''} ${row.nombres ?? ''}`.trim() || '-'}
      </Text>
    ),
  },
  {
    accessor: 'unidad_administrativa',
    title: 'Unidad',
    render: ({ unidad_administrativa }) => (
      <Text size="sm">{unidad_administrativa?.nombre ?? '-'}</Text>
    ),
  },
  {
    accessor: 'contrato_vigente',
    title: 'Cargo',
    render: ({ contrato_vigente }) => (
      <Text size="sm">{contrato_vigente?.puesto?.nombre ?? '-'}</Text>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 100,
    render: ({ contrato_vigente }) => {
      const estado = contrato_vigente?.estado
      if (!estado) return <Text size="sm" c="dimmed">-</Text>
      return (
        <Badge
          color={ESTADO_COLORS[estado] ?? 'gray'}
          variant="light"
          size="sm"
        >
          {ESTADO_LABELS[estado] ?? estado}
        </Badge>
      )
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 80,
    render: (servidor) => (
      <Group gap={4} justify="center">
        <ActionIcon
          variant="subtle"
          color="blue"
          onClick={() => onView(servidor)}
          aria-label="Ver expediente"
        >
          <IconEye size={16} />
        </ActionIcon>
        <ActionIcon
          variant="subtle"
          color="gray"
          onClick={() => onEdit(servidor)}
          aria-label="Editar servidor"
        >
          <IconEdit size={16} />
        </ActionIcon>
      </Group>
    ),
  },
]
