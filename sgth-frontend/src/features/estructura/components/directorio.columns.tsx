import { Badge, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import type { ExtensionConRelaciones } from '@/types/api'

export const directorioColumns: DataTableColumn<ExtensionConRelaciones>[] = [
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
]
