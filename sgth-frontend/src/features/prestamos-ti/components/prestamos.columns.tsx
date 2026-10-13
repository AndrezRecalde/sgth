import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { TONO_ESTADO_PRESTAMO } from '../constants/estados'
import type { PrestamoEquipo } from '../services/tipos'
import { momentoCorto, rangoHorario } from '../utils/agenda'

/** El estado, con «vencido» encima del «en curso» y el motivo de lo que no prosperó. */
export const celdaEstadoPrestamo = (p: PrestamoEquipo) => (
  <Stack gap={2}>
    {p.vencido
      ? <StatusBadge tone="danger">Vencido</StatusBadge>
      : <StatusBadge tone={TONO_ESTADO_PRESTAMO[p.estado]}>{p.estado_etiqueta}</StatusBadge>}
    {p.estado === 'rechazado' && <Text size="xs" c="dimmed" lineClamp={2}>{p.motivo_rechazo}</Text>}
    {p.estado === 'cancelado' && <Text size="xs" c="dimmed" lineClamp={2}>{p.motivo_cancelacion}</Text>}
  </Stack>
)

export const celdaEquiposPrestamo = (p: PrestamoEquipo) => (
  <Stack gap={0}>
    {(p.items ?? []).map((i) => (
      <Text key={i.id} size="sm">
        <b>{i.bien?.codigo_institucional}</b> <Text span size="xs" c="dimmed">{i.bien?.tipo}</Text>
      </Text>
    ))}
  </Stack>
)

/** Lo previsto y, debajo, lo real cuando ya pasó. */
export const celdaHorarioPrestamo = (p: PrestamoEquipo) => (
  <Stack gap={0}>
    <Text size="sm">{rangoHorario(p.inicio_previsto, p.fin_previsto)}</Text>
    {p.entregado_en && (
      <Text size="xs" c="dimmed">
        Retirado {momentoCorto(p.entregado_en)}{p.devuelto_en ? ` · devuelto ${momentoCorto(p.devuelto_en)}` : ''}
      </Text>
    )}
  </Stack>
)

/** «Mis préstamos» del Portal. */
export const columnasMisPrestamos = (accionesDe: (p: PrestamoEquipo) => TableAction[]): DataTableColumn<PrestamoEquipo>[] => [
  { accessor: 'folio', title: 'Folio', width: 130, render: (p) => <Text size="sm" fw={600}>{p.folio}</Text> },
  { accessor: 'items', title: 'Equipos', width: 190, render: celdaEquiposPrestamo },
  { accessor: 'inicio_previsto', title: 'Horario', render: celdaHorarioPrestamo },
  { accessor: 'lugar_uso', title: 'Dónde', width: 180, render: (p) => <Text size="sm" lineClamp={2}>{p.lugar_uso}</Text> },
  { accessor: 'estado', title: 'Estado', width: 170, render: celdaEstadoPrestamo },
  { accessor: 'acciones', title: '', width: 50, render: (p) => <TableActions actions={accionesDe(p)} /> },
]

/** La bandeja de TIC: con quién pide y su unidad. */
export const columnasBandejaPrestamos = (accionesDe: (p: PrestamoEquipo) => TableAction[]): DataTableColumn<PrestamoEquipo>[] => [
  { accessor: 'folio', title: 'Folio', width: 130, render: (p) => <Text size="sm" fw={600}>{p.folio}</Text> },
  {
    accessor: 'servidor',
    title: 'Quién',
    width: 210,
    render: (p) => (
      <Stack gap={0}>
        <Text size="sm">{p.servidor?.nombre}</Text>
        <Text size="xs" c="dimmed">{p.unidad?.acronimo ?? p.unidad?.nombre ?? ''}{p.directo ? ' · directo' : ''}</Text>
      </Stack>
    ),
  },
  { accessor: 'items', title: 'Equipos', width: 180, render: celdaEquiposPrestamo },
  { accessor: 'inicio_previsto', title: 'Horario', render: celdaHorarioPrestamo },
  { accessor: 'estado', title: 'Estado', width: 160, render: celdaEstadoPrestamo },
  { accessor: 'acciones', title: '', width: 50, render: (p) => <TableActions actions={accionesDe(p)} /> },
]
