import { Group, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { ETIQUETA_COLOR, ETIQUETA_PRESENTACION, ETIQUETA_TIPO } from '../constants/consumibles'
import type { Consumible, ModeloCompatible } from '../services/consumiblesService'

/** «HP LaserJet M551, Lexmark MX622DHE». */
export const textoModelos = (modelos: ModeloCompatible[]) =>
  modelos.map((m) => [m.marca, m.modelo].filter(Boolean).join(' ')).join(', ')

/** Lo que describe a un consumible además de su nombre: tipo, color y presentación. */
export const detalleConsumible = (c: Pick<Consumible, 'tipo' | 'color' | 'presentacion'>) =>
  [ETIQUETA_TIPO[c.tipo], c.color ? ETIQUETA_COLOR[c.color] : null, ETIQUETA_PRESENTACION[c.presentacion]].filter(Boolean).join(' · ')

/** El saldo: un guion si lo despacha Bodega, y señalado si bajó del mínimo. */
export function SaldoConsumible({ c }: { c: Pick<Consumible, 'guarda_tic' | 'saldo' | 'stock_minimo' | 'bajo_minimo'> }) {
  if (!c.guarda_tic) return <Text size="sm" c="dimmed">—</Text>

  return (
    <Group gap={6} wrap="nowrap">
      <Text size="sm" fw={600}>{c.saldo ?? 0}</Text>
      {c.bajo_minimo && <StatusBadge tone="warning" variant="dot">Bajo el mínimo</StatusBadge>}
    </Group>
  )
}

export const columnasConsumibles: DataTableColumn<Consumible>[] = [
  {
    accessor: 'descripcion',
    title: 'Consumible',
    render: (c) => (
      <Stack gap={0}>
        <Group gap={6}>
          <Text size="sm" fw={500}>{c.descripcion}</Text>
          {!c.activo && <StatusBadge>Inactivo</StatusBadge>}
        </Group>
        <Text size="xs" c="dimmed">{detalleConsumible(c)}{c.codigo_bodega ? ` · Bodega ${c.codigo_bodega}` : ''}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'modelos',
    title: 'Sirve a',
    render: (c) => (c.modelos.length
      ? <Text size="sm" lineClamp={2}>{textoModelos(c.modelos)}</Text>
      : <Text size="sm" c="dimmed">{c.de_impresion ? 'Sin modelos compatibles' : 'Cualquier equipo'}</Text>),
  },
  {
    accessor: 'guarda_tic',
    title: 'Lo guarda',
    width: 110,
    render: (c) => <StatusBadge>{c.guarda_tic ? 'TIC' : 'Bodega'}</StatusBadge>,
  },
  {
    accessor: 'saldo',
    title: 'Saldo',
    width: 170,
    render: (c) => <SaldoConsumible c={c} />,
  },
  {
    accessor: 'stock_minimo',
    title: 'Mínimo',
    width: 90,
    render: (c) => (c.guarda_tic && c.stock_minimo ? c.stock_minimo : '—'),
  },
]

/** Los compatibles en la ficha de un equipo: el consumible y su saldo. */
export const columnasCompatiblesDelEquipo: DataTableColumn<Consumible>[] = [
  {
    accessor: 'descripcion',
    title: 'Consumible',
    render: (c) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{c.descripcion}</Text>
        <Text size="xs" c="dimmed">{detalleConsumible(c)}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'saldo',
    title: 'Saldo',
    width: 170,
    render: (c) => (c.guarda_tic ? <SaldoConsumible c={c} /> : <Text size="sm" c="dimmed">Lo despacha Bodega</Text>),
  },
]
