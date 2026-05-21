import { Badge, Text, ActionIcon, Group } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import type { ExtensionConRelaciones } from '@/types/api'

type Handlers = {
  onEdit: (record: ExtensionConRelaciones) => void
  onDelete: (record: ExtensionConRelaciones) => void
}

export const getDirectorioColumns = (
  handlers?: Handlers
): DataTableColumn<ExtensionConRelaciones>[] => [
  {
    accessor: 'servidor',
    title: 'Servidor',
    render: ({ servidor }) => (
      <Text size="sm">
        {servidor
          ? `${servidor.nombres ?? ''} ${servidor.apellidos ?? ''}`.trim()
          : 'No asignado'}
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
    accessor: 'numero_extension',
    title: 'Extensión',
    render: ({ numero_extension }) => (
      <Badge color="emerald" variant="light">
        Ext. {numero_extension ?? '-'}
      </Badge>
    ),
  },
  {
    accessor: 'telefono',
    title: 'Teléfono',
    render: ({ servidor }) => (
      <Text size="sm">
        {servidor?.telefono_institucional
          ?? servidor?.telefono_celular
          ?? '-'}
      </Text>
    ),
  },
  {
    accessor: 'email',
    title: 'Email',
    render: ({ servidor }) => (
      <Text size="sm">{servidor?.correo_institucional ?? '-'}</Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 80,
    hidden: !handlers,
    render: (record) => handlers ? (
      <Group gap={4} justify="center">
        <ActionIcon
          variant="subtle"
          color="blue"
          onClick={() => handlers.onEdit(record)}
          aria-label="Editar extensión"
        >
          <IconEdit size={16} />
        </ActionIcon>
        <ActionIcon
          variant="subtle"
          color="red"
          onClick={() => handlers.onDelete(record)}
          aria-label="Eliminar extensión"
        >
          <IconTrash size={16} />
        </ActionIcon>
      </Group>
    ) : null,
  },
]
