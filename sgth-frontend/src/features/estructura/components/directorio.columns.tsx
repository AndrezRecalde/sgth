import { Badge, Text } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import type { DataTableColumn } from 'mantine-datatable'
import type { ExtensionConRelaciones } from '@/types/api'

type Handlers = {
  onEdit:   (record: ExtensionConRelaciones) => void
  onDelete: (record: ExtensionConRelaciones) => void
}

export const getDirectorioColumns = (
  handlers?: Handlers
): DataTableColumn<ExtensionConRelaciones>[] => [
  {
    accessor: 'numero_extension',
    title: 'Extensión',
    width: 110,
    render: ({ numero_extension }) => (
      <Badge color="emerald" variant="light">
        Ext. {numero_extension ?? '-'}
      </Badge>
    ),
  },
  {
    accessor: 'responsable',
    title: 'Responsable',
    render: ({ responsable }) => (
      <Text size="sm">{responsable ?? '-'}</Text>
    ),
  },
  {
    accessor: 'unidad_administrativa',
    title: 'Unidad Administrativa',
    render: ({ unidad_administrativa }) => (
      <Text size="sm">{unidad_administrativa?.nombre ?? '-'}</Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    hidden: !handlers,
    render: (record) => handlers ? (
      <TableActions actions={[
        {
          label: 'Editar extensión',
          icon: <IconEdit size={14} />,
          color: 'blue',
          onClick: () => handlers.onEdit(record),
        },
        {
          label: 'Eliminar extensión',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => handlers.onDelete(record),
        },
      ]} />
    ) : null,
  },
]
