import { Text, Tooltip } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { AusenciaTemporal } from '../services/ausenciaTemporalService'

/** El plazo que resta, con el matiz de que una ausencia puede no tener fin. */
function Restante({ dias }: { dias: number | null }) {
  if (dias === null) {
    return <StatusBadge>Sin fecha de fin</StatusBadge>
  }

  return (
    <StatusBadge tone={dias <= 30 ? 'warning' : 'neutral'}>
      {dias} día{dias === 1 ? '' : 's'}
    </StatusBadge>
  )
}

export const getAusenciaColumns = (): DataTableColumn<AusenciaTemporal>[] => [
  {
    accessor: 'servidor',
    title: 'Servidor ausente',
    render: (a) => (
      <div>
        <Text size="sm" fw={500}>{a.servidor.nombre || '—'}</Text>
        <Text size="xs" c="dimmed">{a.servidor.cedula ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'puesto',
    title: 'Puesto y unidad',
    render: (a) => (
      <div>
        <Text size="sm">{a.puesto ?? '—'}</Text>
        <Text size="xs" c="dimmed">{a.unidad ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'etiqueta',
    title: 'Motivo',
    render: (a) => (
      <div>
        <Text size="sm">{a.etiqueta ?? '—'}</Text>
        {a.codigo_registro && (
          <Text size="xs" c="dimmed" ff="monospace">{a.codigo_registro}</Text>
        )}
      </div>
    ),
  },
  {
    accessor: 'periodo',
    title: 'Período',
    width: 190,
    render: (a) => (
      <div>
        <Text size="sm">{formatFecha(a.desde)} – {a.hasta ? formatFecha(a.hasta) : 'sin fin'}</Text>
        <Restante dias={a.dias_restantes} />
      </div>
    ),
  },
  {
    accessor: 'reemplazo',
    title: 'Cobertura',
    render: (a) => {
      if (!a.reemplazo) {
        return (
          <Tooltip label="Registre un Ingreso y Vinculación enlazado a esta ausencia" withArrow>
            <StatusBadge tone="warning">Sin cubrir</StatusBadge>
          </Tooltip>
        )
      }

      return (
        <div>
          <Text size="sm" fw={500}>{a.reemplazo.servidor.nombre || '—'}</Text>
          <Text size="xs" c="dimmed" ff="monospace">
            {a.reemplazo.numero_contrato ?? 'sin número'}
          </Text>
        </div>
      )
    },
  },
]
