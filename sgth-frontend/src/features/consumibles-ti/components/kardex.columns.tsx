import Link from 'next/link'
import { Anchor, Stack, Text } from '@mantine/core'
import { IconBan } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { formatFecha, formatFechaHora } from '@/lib/fecha'
import { ETIQUETA_DOCUMENTO, ETIQUETA_MOTIVO_AJUSTE, TIPO_MOVIMIENTO } from '../constants/consumibles'
import type { FilaKardex } from '../services/consumiblesService'

/** Qué dice cada movimiento según su tipo: el documento, el equipo y quién retira, o el motivo. */
function Detalle({ m }: { m: FilaKardex }) {
  switch (m.tipo) {
    case 'ingreso':
      return (
        <Stack gap={0}>
          <Text size="sm">{m.tipo_documento ? ETIQUETA_DOCUMENTO[m.tipo_documento] : 'Documento'} {m.numero_documento}</Text>
          {m.fecha_compra && <Text size="xs" c="dimmed">Compra: {formatFecha(m.fecha_compra)}</Text>}
        </Stack>
      )
    case 'entrega':
      return (
        <Stack gap={0}>
          {m.equipo
            ? <Text size="sm"><Anchor size="sm" component={Link} href={ROUTES.TIC.EQUIPO(m.equipo.id)}>{m.equipo.codigo_institucional}</Anchor> {m.equipo.descripcion}</Text>
            : <Text size="sm">Sin equipo</Text>}
          <Text size="xs" c="dimmed">{[m.retira, m.unidad, m.ticket].filter(Boolean).join(' · ')}</Text>
        </Stack>
      )
    case 'ajuste':
      return (
        <Stack gap={0}>
          <Text size="sm">{m.motivo_ajuste ? ETIQUETA_MOTIVO_AJUSTE[m.motivo_ajuste] : 'Ajuste'}</Text>
          {m.observacion && <Text size="xs" c="dimmed">{m.observacion}</Text>}
        </Stack>
      )
    default:
      return <Text size="sm" c="dimmed">{m.observacion ?? 'Saldo contado el día de arranque'}</Text>
  }
}

interface Opciones {
  /** Anular un movimiento; sin él, no hay columna de acciones. */
  onAnular?: (m: FilaKardex) => void
}

export const columnasKardex = ({ onAnular }: Opciones): DataTableColumn<FilaKardex>[] => [
  { accessor: 'fecha', title: 'Fecha', width: 100, render: (m) => formatFecha(m.fecha) },
  {
    accessor: 'tipo',
    title: 'Movimiento',
    width: 115,
    render: (m) => (
      <Stack gap={4} align="flex-start">
        <StatusBadge tone={TIPO_MOVIMIENTO[m.tipo].tono}>{TIPO_MOVIMIENTO[m.tipo].etiqueta}</StatusBadge>
        {m.anulado_en && <StatusBadge tone="danger" variant="dot">Anulado</StatusBadge>}
      </Stack>
    ),
  },
  {
    accessor: 'detalle',
    title: 'Detalle',
    // Con ancho propio: en un teléfono la tabla se desplaza en vez de aplastarlo.
    width: 260,
    render: (m) => (
      <Stack gap={2}>
        <Detalle m={m} />
        {m.tipo !== 'ajuste' && m.tipo !== 'apertura' && m.observacion && <Text size="xs" c="dimmed">{m.observacion}</Text>}
        {m.anulado_en && (
          <Text size="xs" c="dimmed">Anulado por {m.anulado_por ?? '—'}: {m.motivo_anulacion}</Text>
        )}
      </Stack>
    ),
  },
  {
    accessor: 'cantidad',
    title: 'Cantidad',
    width: 85,
    textAlign: 'right',
    render: (m) => (
      <Text size="sm" fw={600} td={m.anulado_en ? 'line-through' : undefined} c={m.anulado_en ? 'dimmed' : undefined}>
        {m.cantidad > 0 ? `+${m.cantidad}` : m.cantidad}
      </Text>
    ),
  },
  {
    accessor: 'saldo',
    title: 'Saldo',
    width: 70,
    textAlign: 'right',
    render: (m) => (m.saldo === null ? <Text size="sm" c="dimmed">—</Text> : <Text size="sm" fw={600}>{m.saldo}</Text>),
  },
  {
    accessor: 'registrado_por',
    title: 'Registró',
    width: 150,
    render: (m) => (
      <Stack gap={0}>
        <Text size="sm">{m.registrado_por ?? '—'}</Text>
        {m.registrado_en && <Text size="xs" c="dimmed">{formatFechaHora(m.registrado_en)}</Text>}
      </Stack>
    ),
  },
  ...(onAnular
    ? [{
        accessor: 'acciones',
        title: '',
        width: 50,
        render: (m: FilaKardex) => (
          <TableActions actions={[
            { label: 'Anular', icon: <IconBan size={14} />, color: 'red', disabled: !!m.anulado_en, onClick: () => onAnular(m) },
          ]} />
        ),
      }]
    : []),
]
