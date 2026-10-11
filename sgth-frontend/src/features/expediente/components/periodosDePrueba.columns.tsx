import { Text, Tooltip } from '@mantine/core'
import { IconArrowBackUp, IconCertificate } from '@tabler/icons-react'
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

/** Cómo termina: el nombramiento definitivo o, en el ascenso, el reintegro. */
function Salida({ p }: { p: PeriodoDePrueba }) {
  if (p.reintegro) {
    return p.reintegro.codigo_registro
      ? <Text size="sm" ff="monospace">Reintegro {p.reintegro.codigo_registro}</Text>
      : <StatusBadge tone="info">Reintegro en trámite</StatusBadge>
  }

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
  onReintegrar: (p: PeriodoDePrueba) => void
}

export const getPeriodoDePruebaColumns = ({
  puedePreparar,
  onPrepararDefinitivo,
  onReintegrar,
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
    accessor: 'motivo',
    title: 'Por qué',
    width: 130,
    render: (p) => (
      <StatusBadge tone={p.motivo === 'ascenso' ? 'info' : 'neutral'}>
        {p.motivo === 'ascenso' ? 'Ascenso' : 'Ingreso'}
      </StatusBadge>
    ),
  },
  {
    accessor: 'puesto',
    title: 'Puesto y unidad',
    render: (p) => (
      <div>
        <Text size="sm">{p.puesto ?? '—'}</Text>
        <Text size="xs" c="dimmed">{p.unidad ?? '—'}</Text>
        {/* El del ascendido, guardado mientras dura la prueba. */}
        {p.puesto_reservado && (
          <Text size="xs" c="dimmed">Se le reserva: {p.puesto_reservado}</Text>
        )}
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
    title: 'Cómo termina',
    render: (p) => <Salida p={p} />,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (p) => {
      const sinSalida = !p.definitivo && !p.reintegro
      const puede = puedePreparar(p.servidor.id)

      return (
        <TableActions
          actions={[
            {
              label: 'Preparar nombramiento definitivo',
              icon: <IconCertificate size={14} />,
              onClick: () => onPrepararDefinitivo(p),
              hidden: !p.concluida || !sinSalida || !puede,
            },
            {
              // Si no supera la prueba, vuelve a su puesto (LOSEP 17 b.5).
              label: 'Reintegrar al puesto anterior',
              icon: <IconArrowBackUp size={14} />,
              onClick: () => onReintegrar(p),
              hidden: p.motivo !== 'ascenso' || !sinSalida || !puede,
            },
          ]}
        />
      )
    },
  },
]
