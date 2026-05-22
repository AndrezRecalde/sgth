import { Text, Group, ActionIcon } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import type { PuestoConRelaciones } from '@/types/api'

type Handlers = {
  onEdit: (puesto: PuestoConRelaciones) => void
  onDelete: (puesto: PuestoConRelaciones) => void
}

export const getPuestoColumns = (
  { onEdit, onDelete }: Handlers
): DataTableColumn<PuestoConRelaciones>[] => [
  {
    accessor: 'denominacion',
    title: 'Puesto',
    render: ({ denominacion }) => (
      <Text size="sm" fw={500}>{denominacion ?? '-'}</Text>
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
    accessor: 'rmu',
    title: 'Remuneración',
    render: ({ rmu }) => (
      <Text size="sm">
        {rmu ? `$${(rmu as number).toFixed(2)}` : '-'}
      </Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 80,
    render: (puesto) => (
      <Group gap={4} justify="center">
        <ActionIcon
          variant="subtle"
          color="blue"
          onClick={() => onEdit(puesto)}
          aria-label="Editar puesto"
        >
          <IconEdit size={16} />
        </ActionIcon>
        <ActionIcon
          variant="subtle"
          color="red"
          onClick={() => onDelete(puesto)}
          aria-label="Eliminar puesto"
        >
          <IconTrash size={16} />
        </ActionIcon>
      </Group>
    ),
  },
]
