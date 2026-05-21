import { Text, Group, ActionIcon } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import type { Puesto } from '@/types/api'

export type PuestoConRelaciones = Puesto & {
  nombre?: string
  codigo?: string
  nivel?: string
  remuneracion?: number | null
  unidad_administrativa?: { nombre?: string }
  unidad_administrativa_id?: number
}

type Handlers = {
  onEdit: (puesto: PuestoConRelaciones) => void
  onDelete: (puesto: PuestoConRelaciones) => void
}

export const getPuestoColumns = (
  { onEdit, onDelete }: Handlers
): DataTableColumn<PuestoConRelaciones>[] => [
  {
    accessor: 'nombre',
    title: 'Puesto',
    render: ({ nombre }) => (
      <Text size="sm" fw={500}>{nombre ?? '-'}</Text>
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
    accessor: 'codigo',
    title: 'Código',
    render: ({ codigo }) => (
      <Text size="sm" c="dimmed">{codigo ?? '-'}</Text>
    ),
  },
  {
    accessor: 'nivel',
    title: 'Nivel',
    render: ({ nivel }) => (
      <Text size="sm">{nivel ?? '-'}</Text>
    ),
  },
  {
    accessor: 'remuneracion',
    title: 'Remuneración',
    render: ({ remuneracion }) => (
      <Text size="sm">
        {remuneracion ? `$${remuneracion.toFixed(2)}` : '-'}
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
