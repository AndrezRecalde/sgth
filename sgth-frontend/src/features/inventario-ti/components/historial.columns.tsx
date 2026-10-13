import type { DataTableColumn } from 'mantine-datatable'
import { Stack, Text } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_BIEN_LABELS, TONO_ESTADO_BIEN } from '../constants/estados'
import type { Asignacion, BienInformatico } from '../services/tipos'
import type { Mantenimiento } from '../services/tipos'
import {
  celdaDescripcionMantenimiento, celdaFechaMantenimiento, celdaQuienMantenimiento, celdaTipoMantenimiento,
} from './mantenimientoCeldas'

type Componente = NonNullable<BienInformatico['componentes']>[number]

/** Las fechas del historial llegan con hora; se muestra el día. */
const fecha = (v: string | null) => (v ? formatFecha(v.slice(0, 10)) : '—')

export const columnasCustodia = (accionesDe: (a: Asignacion) => TableAction[]): DataTableColumn<Asignacion>[] => [
  {
    accessor: 'custodio',
    title: 'Custodio',
    render: (a) => (
      <Stack gap={0}>
        <Text size="sm">{a.custodio?.nombre ?? '—'}</Text>
        {a.usuario ? <Text size="xs" c="dimmed">Lo usa {a.usuario.nombre}</Text> : null}
      </Stack>
    ),
  },
  { accessor: 'fecha_asignacion', title: 'Desde', width: 110, render: (a) => fecha(a.fecha_asignacion) },
  { accessor: 'fecha_devolucion', title: 'Hasta', width: 110, render: (a) => fecha(a.fecha_devolucion) },
  {
    accessor: 'motivo',
    title: 'Motivo',
    width: 110,
    render: (a) => <StatusBadge>{a.motivo === 'traspaso' ? 'Traspaso' : 'Entrega'}</StatusBadge>,
  },
  {
    accessor: 'memorando_numero',
    title: 'Memorando',
    render: (a) => (
      <Stack gap={0}>
        <Text size="sm">{a.memorando_numero ?? '—'}</Text>
        {a.soporte_anterior ? <Text size="xs" c="dimmed">Soporte N.º {a.soporte_anterior} (sistema anterior)</Text> : null}
      </Stack>
    ),
  },
  {
    accessor: 'vigente',
    title: 'Estado',
    width: 110,
    render: (a) => a.vigente ? <StatusBadge tone="success">Vigente</StatusBadge> : <StatusBadge>Devuelto</StatusBadge>,
  },
  { accessor: 'acciones', title: '', width: 50, render: (a) => <TableActions actions={accionesDe(a)} /> },
]

/** Los mantenimientos en la ficha del equipo; los de la lista general están en `mantenimientos.columns`. */
export const columnasMantenimiento = (accionesDe: (m: Mantenimiento) => TableAction[]): DataTableColumn<Mantenimiento>[] => [
  { accessor: 'fecha_mantenimiento', title: 'Fecha', width: 110, render: (m) => celdaFechaMantenimiento(m) },
  { accessor: 'tipo_mantenimiento', title: 'Tipo', width: 140, render: (m) => celdaTipoMantenimiento(m) },
  { accessor: 'descripcion', title: 'Qué se hizo', render: (m) => celdaDescripcionMantenimiento(m) },
  { accessor: 'tecnico', title: 'Quién', width: 170, render: (m) => celdaQuienMantenimiento(m) },
  { accessor: 'acciones', title: '', width: 50, render: (m) => <TableActions actions={accionesDe(m)} /> },
]

export const columnasComponentes: DataTableColumn<Componente>[] = [
  { accessor: 'codigo_institucional', title: 'Código', width: 150 },
  { accessor: 'tipo', title: 'Tipo', render: (c) => c.tipo ?? '—' },
  { accessor: 'modelo', title: 'Modelo', render: (c) => c.modelo ?? '—' },
  {
    accessor: 'estado_operativo',
    title: 'Estado',
    width: 150,
    render: (c) => <StatusBadge tone={TONO_ESTADO_BIEN[c.estado_operativo]}>{ESTADO_BIEN_LABELS[c.estado_operativo]}</StatusBadge>,
  },
]
