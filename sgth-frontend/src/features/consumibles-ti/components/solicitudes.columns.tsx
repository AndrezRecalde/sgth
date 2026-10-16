import Link from 'next/link'
import { Anchor, Stack, Text } from '@mantine/core'
import { IconBan, IconFileTypePdf, IconTruckDelivery } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_SOLICITUD } from '../constants/consumibles'
import type { SolicitudBodega } from '../services/solicitudesService'

const resumenItems = (s: SolicitudBodega) =>
  s.items.map((i) => `${i.cantidad_despachada ?? i.cantidad_solicitada} × ${i.descripcion ?? ''}`).join('; ')

interface Acciones {
  onPdf: (s: SolicitudBodega) => void
  /** Sin permiso de gestionar, no hay despacho ni anulación. */
  onDespachar?: (s: SolicitudBodega) => void
  onAnular?: (s: SolicitudBodega) => void
}

export const columnasSolicitudes = ({ onPdf, onDespachar, onAnular }: Acciones): DataTableColumn<SolicitudBodega>[] => [
  {
    accessor: 'codigo',
    title: 'Solicitud',
    width: 150,
    render: (s) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{s.codigo}</Text>
        <Text size="xs" c="dimmed">{formatFecha(s.fecha)}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'servidor',
    title: 'De parte de',
    // Con ancho propio: en un teléfono la tabla se desplaza en vez de aplastarlo.
    width: 230,
    render: (s) => (
      <Stack gap={0}>
        <Text size="sm">{s.servidor ?? '—'}</Text>
        <Text size="xs" c="dimmed">{[s.unidad, s.equipo?.codigo_institucional].filter(Boolean).join(' · ')}</Text>
      </Stack>
    ),
  },
  { accessor: 'items', title: 'Qué se pidió', width: 260, render: (s) => <Text size="sm" lineClamp={2}>{resumenItems(s)}</Text> },
  {
    accessor: 'ticket',
    title: 'Ticket',
    width: 140,
    render: (s) => (s.ticket_id ? <Anchor size="sm" component={Link} href={ROUTES.TIC.TICKET(s.ticket_id)}>{s.ticket}</Anchor> : <Text size="sm" c="dimmed">Sin ticket</Text>),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 130,
    render: (s) => (
      <Stack gap={2} align="flex-start">
        <StatusBadge tone={ESTADO_SOLICITUD[s.estado].tono}>{ESTADO_SOLICITUD[s.estado].etiqueta}</StatusBadge>
        {s.fecha_despacho && <Text size="xs" c="dimmed">{formatFecha(s.fecha_despacho)}</Text>}
      </Stack>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (s) => (
      <TableActions actions={[
        { label: 'Imprimir (PDF)', icon: <IconFileTypePdf size={14} />, onClick: () => onPdf(s) },
        { label: 'Anotar despacho', icon: <IconTruckDelivery size={14} />, hidden: !onDespachar, disabled: s.estado !== 'pendiente', onClick: () => onDespachar?.(s) },
        { label: 'Anular', icon: <IconBan size={14} />, color: 'red', hidden: !onAnular, disabled: s.estado !== 'pendiente', onClick: () => onAnular?.(s) },
      ]} />
    ),
  },
]

type Item = SolicitudBodega['items'][number]

/** Los renglones al anotar el despacho: lo pedido y lo que despachó Bodega (que se edita en la celda). */
export const columnasDespacho = (cantidad: (indice: number, maximo: number) => React.ReactNode): DataTableColumn<Item>[] => [
  {
    accessor: 'descripcion',
    title: 'Consumible',
    render: (i) => (
      <Stack gap={0}>
        <Text size="sm">{i.descripcion}</Text>
        <Text size="xs" c="dimmed">{[i.codigo_bodega ? `Bodega ${i.codigo_bodega}` : null, i.guarda_tic ? 'Lo guarda TIC: entra y sale' : null].filter(Boolean).join(' · ')}</Text>
      </Stack>
    ),
  },
  { accessor: 'cantidad_solicitada', title: 'Pedido', width: 80, textAlign: 'right' },
  { accessor: 'cantidad_despachada', title: 'Despachado', width: 120, render: (i, n) => cantidad(n, i.cantidad_solicitada) },
]
