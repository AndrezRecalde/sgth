import Link from 'next/link'
import { Anchor, Group, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { formatFecha } from '@/lib/fecha'
import type { AgrupacionConsumos, ReporteConsumos, ReporteSaldoConsumibles } from '../services/reportesTicService'

type Grupo = ReporteConsumos['grupos'][number]

const TITULO_GRUPO: Record<AgrupacionConsumos, string> = { impresora: 'Impresora', area: 'Área', consumible: 'Consumible' }

/** El consumo de cada grupo y su desglose (5.4d). */
export const columnasConsumos = (agrupado: AgrupacionConsumos): DataTableColumn<Grupo>[] => [
  { accessor: 'nombre', title: TITULO_GRUPO[agrupado], width: 280, render: (g) => <Text size="sm" fw={500}>{g.nombre}</Text> },
  { accessor: 'cantidad', title: 'Cantidad', width: 100, textAlign: 'right' },
  { accessor: 'entregas', title: 'Entregas', width: 100, textAlign: 'right' },
  {
    accessor: 'desglose',
    title: agrupado === 'consumible' ? 'Por área' : 'Por consumible',
    width: 320,
    render: (g) => (
      <Text size="xs" c="dimmed" lineClamp={3}>{g.desglose.map((d) => `${d.cantidad} × ${d.nombre}`).join(' · ')}</Text>
    ),
  },
]

type FilaSaldo = ReporteSaldoConsumibles['filas'][number]

/** El saldo de cada consumible que guarda TIC al cierre de la fecha (5.4d). */
export const columnasSaldoConsumibles: DataTableColumn<FilaSaldo>[] = [
  {
    accessor: 'descripcion',
    title: 'Consumible',
    render: (f) => (
      <Stack gap={0}>
        <Group gap={6}>
          <Anchor size="sm" fw={500} component={Link} href={ROUTES.TIC.CONSUMIBLE(f.id)}>{f.descripcion}</Anchor>
          {!f.activo && <StatusBadge>Inactivo</StatusBadge>}
        </Group>
        <Text size="xs" c="dimmed">{[f.tipo, f.presentacion, f.codigo_bodega ? `Bodega ${f.codigo_bodega}` : null].filter(Boolean).join(' · ')}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'saldo',
    title: 'Saldo',
    width: 170,
    render: (f) => (
      <Group gap={6} wrap="nowrap">
        <Text size="sm" fw={600}>{f.saldo}</Text>
        {f.bajo_minimo && <StatusBadge tone="warning" variant="dot">Bajo el mínimo</StatusBadge>}
      </Group>
    ),
  },
  { accessor: 'stock_minimo', title: 'Mínimo', width: 90, render: (f) => f.stock_minimo || '—' },
  { accessor: 'ultimo_movimiento', title: 'Último movimiento', width: 150, render: (f) => (f.ultimo_movimiento ? formatFecha(f.ultimo_movimiento) : '—') },
]
