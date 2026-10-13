import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { CountBadge, StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { ETIQUETA_PRIORIDAD, TONO_PRIORIDAD } from '../constants/estados'
import type { CategoriaTicketConfig, PrioridadTicket, SlaRegla, TecnicoHelpdesk } from '../services/tipos'

type Acciones<T> = (registro: T) => TableAction[]

const accionesCol = <T,>(accionesDe: Acciones<T>): DataTableColumn<T> => ({
  accessor: 'acciones',
  title: '',
  width: 50,
  render: (r) => <TableActions actions={accionesDe(r)} />,
})

const horas = (h: number) => `${h.toLocaleString('es-EC', { maximumFractionDigits: 1 })} h`

export const columnasCategorias = (accionesDe: Acciones<CategoriaTicketConfig>): DataTableColumn<CategoriaTicketConfig>[] => [
  {
    accessor: 'nombre',
    title: 'Categoría',
    render: (c) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{c.nombre}</Text>
        {c.descripcion && <Text size="xs" c="dimmed" lineClamp={1}>{c.descripcion}</Text>}
      </Stack>
    ),
  },
  { accessor: 'unidad_tic', title: 'La atiende', width: 220, render: (c) => c.unidad_tic ?? <Text size="sm" c="dimmed">Sin definir</Text> },
  { accessor: 'es_hardware', title: 'Equipo', width: 110, render: (c) => (c.es_hardware ? 'Lo pide' : '—') },
  { accessor: 'tickets_count', title: 'Tickets', width: 100, render: (c) => <CountBadge>{c.tickets_count}</CountBadge> },
  {
    accessor: 'activa',
    title: 'Estado',
    width: 120,
    render: (c) => <StatusBadge tone={c.activa ? 'success' : 'neutral'}>{c.activa ? 'Activa' : 'Inactiva'}</StatusBadge>,
  },
  accionesCol(accionesDe),
]

/**
 * Una fila de la tabla del SLA: la regla guardada o, para una prioridad sin
 * regla general, el plazo por defecto que rige (`config/helpdesk.php`).
 */
export type FilaSla = {
  clave: string
  prioridad: PrioridadTicket
  categoria: string | null
  respuesta: number
  resolucion: number
  regla: SlaRegla | null
}

export const columnasSla = (accionesDe: Acciones<FilaSla>): DataTableColumn<FilaSla>[] => [
  {
    accessor: 'prioridad',
    title: 'Prioridad',
    width: 130,
    render: (f) => <StatusBadge tone={TONO_PRIORIDAD[f.prioridad]}>{ETIQUETA_PRIORIDAD[f.prioridad]}</StatusBadge>,
  },
  { accessor: 'categoria', title: 'Se aplica a', render: (f) => f.categoria ?? 'Todas las categorías' },
  { accessor: 'respuesta', title: 'Primera respuesta', width: 160, render: (f) => horas(f.respuesta) },
  { accessor: 'resolucion', title: 'Resolución', width: 130, render: (f) => horas(f.resolucion) },
  {
    accessor: 'origen',
    title: 'Origen',
    width: 140,
    render: (f) => <StatusBadge tone={f.regla ? 'info' : 'neutral'}>{f.regla ? 'Fijado por TIC' : 'Por defecto'}</StatusBadge>,
  },
  accionesCol(accionesDe),
]

export const columnasTecnicos = (accionesDe: Acciones<TecnicoHelpdesk>): DataTableColumn<TecnicoHelpdesk>[] => [
  {
    accessor: 'nombre',
    title: 'Técnico',
    render: (t) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{t.nombre ?? '—'}</Text>
        {t.usuario_ti && <Text size="xs" c="dimmed">{t.usuario_ti}</Text>}
      </Stack>
    ),
  },
  { accessor: 'unidad_tic', title: 'Subunidad de TIC', width: 240, render: (t) => t.unidad_tic ?? '—' },
  { accessor: 'nivel', title: 'Nivel', width: 90 },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 120,
    render: (t) => <StatusBadge tone={t.estado ? 'success' : 'neutral'}>{t.estado ? 'Atiende' : 'Pausado'}</StatusBadge>,
  },
  accionesCol(accionesDe),
]
