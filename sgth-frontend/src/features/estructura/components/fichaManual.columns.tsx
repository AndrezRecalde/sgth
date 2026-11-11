import { Text } from '@mantine/core'
import { IconClipboardCheck } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { CountBadge, StatusBadge, TableActions } from '@/components/ui'
import { estadoFichaManual } from '../constants/manualPuestos'
import type { FichaManual } from '../services/manualPuestosService'

/*
| Las fichas del Manual de Puestos en la revisión (P4, PR 12): cada una con su
| estado, por qué, y el puesto que se le sugiere o en que se cargó.
*/
export const getFichasManualColumns = (
  { alRevisar }: { alRevisar: (f: FichaManual) => void },
): DataTableColumn<FichaManual>[] => [
  {
    accessor: 'denominacion',
    title: 'Ficha del manual',
    render: ({ denominacion, gestion, subproceso, grupo_ocupacional, grado }) => (
      <div>
        <Text size="sm" fw={500}>{denominacion || 'Sin denominación'}</Text>
        <Text size="xs" c="dimmed" lineClamp={2}>{[gestion, subproceso].filter(Boolean).join(' / ') || '—'}</Text>
        <Text size="xs" c="dimmed">{[grupo_ocupacional, grado ? `grado ${grado}` : null].filter(Boolean).join(', ')}</Text>
      </div>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 260,
    render: ({ estado, motivo }) => {
      const { label, tone } = estadoFichaManual(estado)
      return (
        <div>
          <StatusBadge tone={tone}>{label}</StatusBadge>
          {estado !== 'lista' && estado !== 'cargada' && <Text size="xs" c="dimmed" mt={4} lineClamp={3}>{motivo}</Text>}
        </div>
      )
    },
  },
  {
    accessor: 'puesto',
    title: 'Puesto del SGTH',
    render: ({ puesto, estado, cargada_por }) => {
      if (!puesto) return <Text size="sm" c="dimmed">—</Text>
      return (
        <div>
          <Text size="sm">{puesto.cargo ?? `Puesto ${puesto.id}`}{puesto.grado ? <Text span size="xs" c="dimmed"> · grado {puesto.grado}</Text> : null}</Text>
          <Text size="xs" c="dimmed">{puesto.unidad ?? '—'}</Text>
          {estado === 'cargada' && cargada_por && <Text size="xs" c="dimmed">Cargada por {cargada_por}</Text>}
        </div>
      )
    },
  },
  {
    accessor: 'avisos_count',
    title: 'Avisos',
    width: 80,
    textAlign: 'center',
    render: ({ avisos_count }) => avisos_count > 0
      ? <CountBadge tone="warning">{avisos_count}</CountBadge>
      : <Text size="sm" c="dimmed">—</Text>,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (f) => (
      <TableActions actions={[{
        label: f.estado === 'cargada' ? 'Ver la ficha' : 'Revisar y cargar',
        icon: <IconClipboardCheck size={14} />,
        onClick: () => alRevisar(f),
      }]} />
    ),
  },
]
