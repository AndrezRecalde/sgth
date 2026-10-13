import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_BIEN_LABELS, TONO_ESTADO_BIEN } from '../constants/estados'
import type { BienInformatico, HistorialBien } from '../services/tipos'

type Asignacion = HistorialBien['asignaciones'][number]
type Mantenimiento = HistorialBien['mantenimientos'][number]
type Componente = NonNullable<BienInformatico['componentes']>[number]

/** Las fechas del historial llegan con hora; se muestra el día. */
const fecha = (v: string | null) => (v ? formatFecha(v.slice(0, 10)) : '—')

export const columnasCustodia: DataTableColumn<Asignacion>[] = [
  { accessor: 'servidor', title: 'Custodio', render: (a) => a.servidor ? `${a.servidor.apellido} ${a.servidor.nombre}` : '—' },
  { accessor: 'fecha_asignacion', title: 'Desde', width: 120, render: (a) => fecha(a.fecha_asignacion) },
  { accessor: 'fecha_devolucion', title: 'Hasta', width: 120, render: (a) => fecha(a.fecha_devolucion) },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 120,
    render: (a) => a.fecha_devolucion
      ? <StatusBadge>Devuelto</StatusBadge>
      : <StatusBadge tone="success">Vigente</StatusBadge>,
  },
  { accessor: 'observaciones', title: 'Observaciones', render: (a) => a.observaciones ?? '—' },
]

export const columnasMantenimiento: DataTableColumn<Mantenimiento>[] = [
  { accessor: 'fecha_mantenimiento', title: 'Fecha', width: 120, render: (m) => fecha(m.fecha_mantenimiento) },
  { accessor: 'tipo_mantenimiento', title: 'Tipo', width: 140, render: (m) => <StatusBadge>{m.tipo_mantenimiento}</StatusBadge> },
  { accessor: 'descripcion', title: 'Qué se hizo' },
  { accessor: 'tecnico', title: 'Técnico', width: 140, render: (m) => m.tecnico?.usuario_ti ?? '—' },
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
