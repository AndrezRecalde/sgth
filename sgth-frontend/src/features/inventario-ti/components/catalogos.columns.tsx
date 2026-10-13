import { Group, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { CountBadge, StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { ContratoVigencia, Marca, TipoBien, Ubicacion } from '../services/tipos'

type Acciones<T> = (registro: T) => TableAction[]

const accionesCol = <T,>(accionesDe: Acciones<T>): DataTableColumn<T> => ({
  accessor: 'acciones',
  title: '',
  width: 50,
  render: (r) => <TableActions actions={accionesDe(r)} />,
})

/** Cuántos equipos lo usan: es lo que decide si se puede eliminar. */
const equiposCol = <T extends { bienes_count: number | null }>(): DataTableColumn<T> => ({
  accessor: 'bienes_count',
  title: 'Equipos',
  width: 100,
  render: (r) => <CountBadge>{r.bienes_count ?? 0}</CountBadge>,
})

export const columnasTipos = (accionesDe: Acciones<TipoBien>): DataTableColumn<TipoBien>[] => [
  { accessor: 'nombre', title: 'Tipo', width: 180 },
  {
    accessor: 'subtipos',
    title: 'Subtipos',
    render: (t) => t.subtipos?.length
      ? <Group gap={4}>{t.subtipos.map((s) => <StatusBadge key={s.id}>{s.nombre}</StatusBadge>)}</Group>
      : <Text size="sm" c="dimmed">—</Text>,
  },
  {
    accessor: 'anios_vida_util',
    title: 'Vida útil',
    width: 130,
    render: (t) => t.anios_vida_util > 0
      ? `${t.anios_vida_util} años`
      : <StatusBadge tone="warning">Sin definir</StatusBadge>,
  },
  equiposCol<TipoBien>(),
  accionesCol(accionesDe),
]

export const columnasMarcas = (accionesDe: Acciones<Marca>): DataTableColumn<Marca>[] => [
  { accessor: 'nombre', title: 'Marca' },
  equiposCol<Marca>(),
  accionesCol(accionesDe),
]

export const columnasUbicaciones = (accionesDe: Acciones<Ubicacion>): DataTableColumn<Ubicacion>[] => [
  { accessor: 'sede', title: 'Sede' },
  { accessor: 'piso', title: 'Piso', width: 120, render: (u) => u.piso ?? '—' },
  { accessor: 'descripcion', title: 'Descripción', render: (u) => u.descripcion ?? '—' },
  equiposCol<Ubicacion>(),
  accionesCol(accionesDe),
]

export const columnasContratos = (accionesDe: Acciones<ContratoVigencia>): DataTableColumn<ContratoVigencia>[] => [
  { accessor: 'proceso', title: 'Proceso', width: 200 },
  { accessor: 'proveedor', title: 'Proveedor', render: (c) => c.proveedor ?? '—' },
  { accessor: 'administrador', title: 'Administrador', render: (c) => c.administrador?.nombre ?? '—' },
  {
    accessor: 'fecha_fin',
    title: 'Vigencia',
    width: 210,
    render: (c) => (
      <Group gap={6} wrap="nowrap">
        <Text size="sm">{c.fecha_inicio ? formatFecha(c.fecha_inicio) : '—'} – {c.fecha_fin ? formatFecha(c.fecha_fin) : '—'}</Text>
        <StatusBadge tone={c.vigente ? 'success' : 'neutral'}>{c.vigente ? 'Vigente' : 'Vencido'}</StatusBadge>
      </Group>
    ),
  },
  { accessor: 'cronograma', title: 'Mantenimientos', width: 140, render: (c) => <CountBadge>{c.cronograma?.length ?? 0}</CountBadge> },
  equiposCol<ContratoVigencia>(),
  accionesCol(accionesDe),
]

type Subtipo = NonNullable<TipoBien['subtipos']>[number]

export const columnasSubtipos = (accionesDe: Acciones<Subtipo>): DataTableColumn<Subtipo>[] => [
  { accessor: 'nombre', title: 'Subtipo' },
  accionesCol(accionesDe),
]
