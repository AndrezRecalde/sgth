import { Text, Badge } from '@mantine/core'
import { IconEye, IconEdit } from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
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
    accessor: 'nombre',
    title: 'Nombre completo',
    render: (row) => {
      const nombre = [
        row.apellido,
        row.segundo_apellido,
        row.nombre,
        row.segundo_nombre,
      ].filter(Boolean).join(' ')
      return (
        <Text size="sm" fw={500}>
          {nombre || '-'}
        </Text>
      )
    },
  },
  {
    accessor: 'unidad_administrativa',
    title: 'Unidad',
    render: ({ unidad_administrativa }) => (
      <Text size="sm">{unidad_administrativa?.nombre ?? '-'}</Text>
    ),
  },
  {
    accessor: 'puesto',
    title: 'Cargo',
    render: (row) => {
      const cargoNombre = (row as ServidorConRelaciones & {
        puesto?: { cargo?: { nombre?: string } | null } | null
      }).puesto?.cargo?.nombre
      return (
        <Text size="sm" c="dimmed">
          {cargoNombre ?? '-'}
        </Text>
      )
    },
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 100,
    render: ({ estado }) => {
      if (!estado) return <Text size="sm" c="dimmed">-</Text>
      return (
        <Badge
          color={estado ? 'emerald' : 'gray'}
          variant="light"
          size="sm"
        >
          {estado ? 'Activo' : 'Inactivo'}
        </Badge>
      )
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (servidor) => (
      <TableActions actions={[
        {
          label: 'Ver expediente',
          icon: <IconEye size={14} />,
          color: 'blue',
          onClick: () => onView(servidor),
        },
        {
          label: 'Editar datos',
          icon: <IconEdit size={14} />,
          color: 'gray',
          onClick: () => onEdit(servidor),
        },
      ]} />
    ),
  },
]
