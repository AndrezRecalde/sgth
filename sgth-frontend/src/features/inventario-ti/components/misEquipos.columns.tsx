import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { Asignacion } from '../services/tipos'

/**
 * Los equipos del servidor. «A su cargo» es la custodia que firmó; «Lo usa»,
 * el equipo de otro custodio que él tiene en su puesto.
 */
export const columnasMisEquipos = (servidorId: number | null, accionesDe: (a: Asignacion) => TableAction[]): DataTableColumn<Asignacion>[] => [
  {
    accessor: 'bien',
    title: 'Equipo',
    render: (a) => (
      <Stack gap={0}>
        <Text size="sm" fw={600}>{a.bien?.codigo_institucional}</Text>
        <Text size="xs" c="dimmed">{[a.bien?.tipo, a.bien?.subtipo, a.bien?.marca, a.bien?.modelo].filter(Boolean).join(' · ')}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'servidor_id',
    title: 'Su relación',
    width: 170,
    render: (a) => a.servidor_id === servidorId
      ? <StatusBadge>A su cargo</StatusBadge>
      : <Stack gap={2}><StatusBadge>Lo usa</StatusBadge><Text size="xs" c="dimmed">Custodio: {a.custodio?.nombre}</Text></Stack>,
  },
  { accessor: 'fecha_asignacion', title: 'Desde', width: 120, render: (a) => (a.fecha_asignacion ? formatFecha(a.fecha_asignacion) : '—') },
  { accessor: 'ubicacion', title: 'Ubicación', render: (a) => a.bien?.ubicacion ?? '—' },
  { accessor: 'acciones', title: '', width: 50, render: (a) => <TableActions actions={accionesDe(a)} /> },
]
