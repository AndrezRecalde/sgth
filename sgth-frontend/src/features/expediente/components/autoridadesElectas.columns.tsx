import { Text } from '@mantine/core'
import { IconCalendarEvent } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { AutoridadElecta } from '../services/autoridadElectaService'

/** Lo que le queda al período, o que no tiene fin fijado. */
function Restante({ a }: { a: AutoridadElecta }) {
  if (!a.hasta) return <StatusBadge tone="warning">Sin fin del período</StatusBadge>
  if (a.dias_restantes === null) return null
  if (a.dias_restantes < 0) return <StatusBadge tone="info">Terminado</StatusBadge>

  return (
    <StatusBadge tone={a.dias_restantes <= 30 ? 'warning' : 'neutral'}>
      {a.dias_restantes} día{a.dias_restantes === 1 ? '' : 's'}
    </StatusBadge>
  )
}

/** Lo que sigue: el cese en curso, o el período nuevo de la autoridad reelegida. */
function Siguiente({ a }: { a: AutoridadElecta }) {
  if (a.cese) {
    return (
      <div>
        <StatusBadge tone="warning">{a.cese.causal ?? 'Cese'}</StatusBadge>
        <Text size="xs" c="dimmed">
          {a.cese.codigo_registro ?? 'En trámite'} · rige el {formatFecha(a.cese.fecha_efectiva)}
        </Text>
      </div>
    )
  }

  if (a.siguiente) {
    return (
      <div>
        <StatusBadge tone="info">Reelección</StatusBadge>
        <Text size="xs" c="dimmed">
          {a.siguiente.codigo_registro ?? 'En trámite'} · desde el {formatFecha(a.siguiente.fecha_efectiva)}
        </Text>
      </div>
    )
  }

  return <Text size="sm" c="dimmed">—</Text>
}

interface Opciones {
  /** ¿Quien mira puede preparar acciones para ese servidor? */
  puedePreparar: (servidorId: number | null) => boolean
  onFijarFin: (a: AutoridadElecta) => void
}

export const getAutoridadElectaColumns = ({
  puedePreparar,
  onFijarFin,
}: Opciones): DataTableColumn<AutoridadElecta>[] => [
  {
    accessor: 'servidor',
    title: 'Autoridad',
    render: (a) => (
      <div>
        <Text size="sm" fw={500}>{a.servidor.nombre || '—'}</Text>
        <Text size="xs" c="dimmed">{a.dignidad ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'periodo',
    title: 'Período',
    width: 210,
    render: (a) => (
      <div>
        <Text size="sm">{formatFecha(a.desde)} – {a.hasta ? formatFecha(a.hasta) : '…'}</Text>
        <Restante a={a} />
      </div>
    ),
  },
  {
    accessor: 'inicio',
    title: 'Inicio',
    render: (a) => (
      <div>
        <Text size="sm" ff="monospace">{a.inicio?.codigo_registro ?? '—'}</Text>
        {a.credencial && <Text size="xs" c="dimmed">Credencial {a.credencial}</Text>}
      </div>
    ),
  },
  {
    accessor: 'siguiente',
    title: 'Lo que sigue',
    render: (a) => <Siguiente a={a} />,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (a) => (
      <TableActions
        actions={[
          {
            label: 'Fijar el fin del período',
            icon: <IconCalendarEvent size={14} />,
            onClick: () => onFijarFin(a),
            hidden: !a.puede_fijar_fin || !puedePreparar(a.servidor.id),
          },
        ]}
      />
    ),
  },
]
