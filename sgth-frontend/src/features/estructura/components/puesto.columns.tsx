import { Group, Text } from '@mantine/core'
import { IconEdit, IconTrash, IconList } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import type { DataTableColumn } from 'mantine-datatable'
import type { PuestoConRelaciones } from '@/types/api'
import { REGIMEN_LABELS, REGIMEN_TONOS } from '@/lib/regimen'

type Handlers = {
  onEdit:        (puesto: PuestoConRelaciones) => void
  onDelete:      (puesto: PuestoConRelaciones) => void
  onActividades: (puesto: PuestoConRelaciones) => void
}

export const getPuestoColumns = (
  { onEdit, onDelete, onActividades }: Handlers
): DataTableColumn<PuestoConRelaciones>[] => [
  {
    accessor: 'cargo',
    title: 'Cargo',
    render: ({ cargo, es_jefe }) => (
      <div>
        <Text size="sm" fw={500}>{cargo?.nombre ?? '-'}</Text>
        {es_jefe && (
          <Group gap={4} mt={2}>
            <StatusBadge tone="success" size="xs">Jefe</StatusBadge>
          </Group>
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
    width: 170,
    render: ({ regimen_laboral }) => regimen_laboral ? (
      <StatusBadge tone={REGIMEN_TONOS[regimen_laboral] ?? 'neutral'}>
        {REGIMEN_LABELS[regimen_laboral] ?? regimen_laboral}
      </StatusBadge>
    ) : <Text size="sm" c="dimmed">-</Text>,
  },
  {
    accessor: 'plazas',
    title: 'Plazas',
    width: 90,
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
          label: 'Actividades del puesto',
          icon: <IconList size={14} />,
          color: 'emerald',
          onClick: () => onActividades(puesto),
        },
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
