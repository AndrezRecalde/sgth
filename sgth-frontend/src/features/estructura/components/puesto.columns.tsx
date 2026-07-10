import { Text, Badge } from '@mantine/core'
import { IconEdit, IconTrash, IconList } from '@tabler/icons-react'
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

const CLASIFICACION_COLORS: Record<string, string> = {
  empleado:   'gray',
  contratado: 'orange',
  obrero:     'violet',
}

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
        <div style={{ display: 'flex', gap: 4, marginTop: 2 }}>
          {cargo?.clasificacion_personal && (
            <Badge
              size="xs"
              variant="dot"
              color={CLASIFICACION_COLORS[cargo.clasificacion_personal] ?? 'gray'}
            >
              {cargo.clasificacion_personal}
            </Badge>
          )}
          {es_jefe && (
            <Badge size="xs" variant="dot" color="emerald">
              Jefe
            </Badge>
          )}
        </div>
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
    width: 110,
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
