import { Text, Badge } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import type { DataTableColumn } from 'mantine-datatable'
import type { PuestoConRelaciones } from '@/types/api'

const REGIMEN_LABELS: Record<string, string> = {
  losep:          'LOSEP',
  codigo_trabajo: 'Cód. Trabajo',
}

const REGIMEN_COLORS: Record<string, string> = {
  losep:          'emerald',
  codigo_trabajo: 'blue',
}

type Handlers = {
  onEdit:   (puesto: PuestoConRelaciones) => void
  onDelete: (puesto: PuestoConRelaciones) => void
}

export const getPuestoColumns = (
  { onEdit, onDelete }: Handlers
): DataTableColumn<PuestoConRelaciones>[] => [
  {
    accessor: 'denominacion',
    title: 'Puesto',
    render: ({ denominacion, es_jefe }) => (
      <div>
        <Text size="sm" fw={500}>{denominacion ?? '-'}</Text>
        {es_jefe && (
          <Text size="xs" c="emerald">Jefe de unidad</Text>
        )}
      </div>
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
    accessor: 'regimen_laboral',
    title: 'Régimen',
    width: 120,
    render: ({ regimen_laboral }) => regimen_laboral ? (
      <Badge
        color={REGIMEN_COLORS[regimen_laboral] ?? 'gray'}
        variant="light"
        size="sm"
      >
        {REGIMEN_LABELS[regimen_laboral] ?? regimen_laboral}
      </Badge>
    ) : <Text size="sm" c="dimmed">-</Text>,
  },
  {
    accessor: 'plazas',
    title: 'Plazas',
    width: 70,
    render: ({ plazas }) => (
      <Text size="sm" ta="center">{plazas ?? 1}</Text>
    ),
  },
  {
    accessor: 'rmu',
    title: 'RMU',
    width: 100,
    render: ({ rmu }) => (
      <Text size="sm">
        {rmu ? `$${Number(rmu).toFixed(2)}` : '-'}
      </Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (puesto) => (
      <TableActions actions={[
        {
          label: 'Editar puesto',
          icon: <IconEdit size={14} />,
          color: 'blue',
          onClick: () => onEdit(puesto),
        },
        {
          label: 'Eliminar puesto',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => onDelete(puesto),
        },
      ]} />
    ),
  },
]
