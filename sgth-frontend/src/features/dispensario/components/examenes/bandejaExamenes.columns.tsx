import { Group, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFechaMes, hoyIso } from '@/lib/fecha'
import { pacienteDeOrden, type OrdenBandeja } from '../../services/bandejaExamenesService'

/** Días desde que se pidió, contra el día de hoy en la hora local: lo que lleva esperando. */
function diasDesde(fecha: string): number {
  return Math.round((Date.parse(`${hoyIso()}T00:00:00Z`) - Date.parse(`${fecha.slice(0, 10)}T00:00:00Z`)) / 86_400_000)
}

export const columnasBandejaExamenes = (
  accionesDe: (o: OrdenBandeja) => TableAction[],
): DataTableColumn<OrdenBandeja>[] => [
  {
    accessor: 'folio',
    title: 'Orden',
    width: 170,
    render: (o) => (
      <Stack gap={0}>
        <Group gap={6} wrap="nowrap">
          <Text size="sm" fw={600} ff="monospace">{o.folio}</Text>
          {o.prioridad === 'urgente' && <StatusBadge size="xs" tone="warning">Urgente</StatusBadge>}
        </Group>
        <Text size="xs" c="dimmed">{formatFechaMes(o.fecha_solicitud)}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'paciente',
    title: 'Paciente',
    render: (o) => {
      const { nombre, cedula } = pacienteDeOrden(o)
      return (
        <Stack gap={0}>
          <Text size="sm">{nombre}</Text>
          {cedula && <Text size="xs" c="dimmed">{cedula}</Text>}
        </Stack>
      )
    },
  },
  {
    accessor: 'items',
    title: 'Exámenes',
    render: (o) => {
      const recibidos = o.items.filter((i) => i.estado !== 'pendiente').length
      const pendientes = o.items.filter((i) => i.estado === 'pendiente').map((i) => i.examen?.nombre).join(', ')
      return (
        <Stack gap={2}>
          <Text size="xs" fw={600}>{recibidos} de {o.items.length} resueltos</Text>
          <Text size="xs" c="dimmed" lineClamp={2} title={pendientes}>Faltan: {pendientes}</Text>
        </Stack>
      )
    },
  },
  {
    accessor: 'solicitante',
    title: 'Pidió',
    width: 170,
    render: (o) => o.solicitante?.servidor
      ? `${o.solicitante.servidor.nombre} ${o.solicitante.servidor.apellido}`
      : '—',
  },
  {
    accessor: 'espera',
    title: 'Espera',
    width: 130,
    render: (o) => {
      const dias = diasDesde(o.fecha_solicitud)
      return o.vencida
        ? <StatusBadge tone="danger">Vencida · {dias} d</StatusBadge>
        : <Text size="sm">{dias === 0 ? 'Hoy' : `${dias} ${dias === 1 ? 'día' : 'días'}`}</Text>
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (o) => <TableActions actions={accionesDe(o)} />,
  },
]
