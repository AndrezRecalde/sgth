import { Text, Tooltip } from '@mantine/core'
import { IconCertificate } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { PeriodoDePrueba } from '../services/periodoDePruebaService'

/** Lo que falta de la prueba, o que ya concluyó. */
function Restante({ p }: { p: PeriodoDePrueba }) {
  if (p.concluida) return <StatusBadge tone="info">Concluido</StatusBadge>
  if (p.dias_restantes === null) return null

  return (
    <StatusBadge tone={p.dias_restantes <= 15 ? 'warning' : 'neutral'}>
      {p.dias_restantes} día{p.dias_restantes === 1 ? '' : 's'}
    </StatusBadge>
  )
}

/** El nombramiento definitivo, en trámite o registrado. */
function Definitivo({ p }: { p: PeriodoDePrueba }) {
  if (!p.definitivo) {
    return p.concluida
      ? (
        <Tooltip label="Prepárelo desde el menú de la fila" withArrow>
          <StatusBadge tone="warning">Pendiente</StatusBadge>
        </Tooltip>
      )
      : <Text size="sm" c="dimmed">—</Text>
  }

  return p.definitivo.codigo_registro
    ? <Text size="sm" ff="monospace">{p.definitivo.codigo_registro}</Text>
    : <StatusBadge tone="info">En trámite</StatusBadge>
}

interface Opciones {
  /** ¿Quien mira puede preparar acciones para ese servidor? */
  puedePreparar: (servidorId: number | null) => boolean
  onPrepararDefinitivo: (p: PeriodoDePrueba) => void
}

export const getPeriodoDePruebaColumns = ({
  puedePreparar,
  onPrepararDefinitivo,
}: Opciones): DataTableColumn<PeriodoDePrueba>[] => [
  {
    accessor: 'servidor',
    title: 'Servidor',
    render: (p) => (
      <div>
        <Text size="sm" fw={500}>{p.servidor.nombre || '—'}</Text>
        <Text size="xs" c="dimmed">{p.servidor.cedula ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'puesto',
    title: 'Puesto y unidad',
    render: (p) => (
      <div>
        <Text size="sm">{p.puesto ?? '—'}</Text>
        <Text size="xs" c="dimmed">{p.unidad ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'periodo',
    title: 'Período de prueba',
    width: 200,
    render: (p) => (
      <div>
        <Text size="sm">{formatFecha(p.desde)} – {formatFecha(p.hasta)}</Text>
        <Restante p={p} />
      </div>
    ),
  },
  {
    accessor: 'definitivo',
    title: 'Nombramiento definitivo',
    render: (p) => <Definitivo p={p} />,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (p) => (
      <TableActions
        actions={[
          {
            label: 'Preparar nombramiento definitivo',
            icon: <IconCertificate size={14} />,
            onClick: () => onPrepararDefinitivo(p),
            hidden: !p.concluida || !!p.definitivo || !puedePreparar(p.servidor.id),
          },
        ]}
      />
    ),
  },
]
