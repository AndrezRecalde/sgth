import Link from 'next/link'
import { Anchor, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { formatFecha, formatFechaHora } from '@/lib/fecha'
import type { CifrasTickets, EquipoInventario, ReporteObsolescencia, ReporteSatisfaccion } from '../services/reportesTicService'

const numero = (v: number | null, sufijo = '') => (v === null ? '—' : `${v.toLocaleString('es-EC', { maximumFractionDigits: 1 })}${sufijo}`)

const codigo = <T extends { id: number; codigo_institucional: string }>(): DataTableColumn<T> => ({
  accessor: 'codigo_institucional',
  title: 'Código',
  width: 100,
  render: (e) => <Anchor size="sm" component={Link} href={ROUTES.TIC.EQUIPO(e.id)}>{e.codigo_institucional}</Anchor>,
})

const equipo = (e: { tipo: string | null; marca: string | null; modelo: string | null }) => (
  <Stack gap={0}>
    <Text size="sm">{e.tipo ?? '—'}</Text>
    <Text size="xs" c="dimmed">{[e.marca, e.modelo].filter(Boolean).join(' ') || '—'}</Text>
  </Stack>
)

export const columnasInventario = (porUbicacion: boolean): DataTableColumn<EquipoInventario>[] => [
  codigo<EquipoInventario>(),
  { accessor: 'tipo', title: 'Equipo', render: equipo },
  { accessor: 'numero_serie', title: 'Serie', width: 130, render: (e) => e.numero_serie ?? '—' },
  { accessor: 'estado', title: 'Estado', width: 140, render: (e) => `${e.estado}${e.condicion ? ` · ${e.condicion}` : ''}` },
  {
    accessor: 'custodio',
    title: 'Custodio',
    render: (e) => (
      <Stack gap={0}>
        <Text size="sm" c={e.custodio ? undefined : 'dimmed'}>{e.custodio ?? 'Sin custodio'}</Text>
        {e.usuario && <Text size="xs" c="dimmed">Usa: {e.usuario}</Text>}
      </Stack>
    ),
  },
  { accessor: porUbicacion ? 'unidad' : 'ubicacion', title: porUbicacion ? 'Unidad' : 'Ubicación', render: (e) => (porUbicacion ? e.unidad : e.ubicacion) ?? '—' },
]

type EquipoObsoleto = ReporteObsolescencia['equipos'][number]

export const columnasObsolescencia: DataTableColumn<EquipoObsoleto>[] = [
  codigo<EquipoObsoleto>(),
  { accessor: 'tipo', title: 'Equipo', render: equipo },
  { accessor: 'anios', title: 'Años', width: 80, render: (e) => e.anios ?? '—' },
  {
    accessor: 'fin_vida_util',
    title: 'Fin de vida útil',
    width: 160,
    render: (e) => (
      <Stack gap={2}>
        <Text size="sm">{formatFecha(e.fin_vida_util)}</Text>
        <StatusBadge size="xs" tone={e.situacion === 'cumplida' ? 'danger' : 'warning'}>{e.situacion === 'cumplida' ? 'Cumplida' : 'Próxima'}</StatusBadge>
      </Stack>
    ),
  },
  { accessor: 'condicion', title: 'Condición', width: 110, render: (e) => e.condicion ?? '—' },
  { accessor: 'correctivos', title: 'Correctivos', width: 110 },
  {
    accessor: 'custodio',
    title: 'Custodio',
    render: (e) => (
      <Stack gap={0}>
        <Text size="sm" c={e.custodio ? undefined : 'dimmed'}>{e.custodio ?? 'Sin custodio'}</Text>
        {e.unidad && <Text size="xs" c="dimmed">{e.unidad}</Text>}
      </Stack>
    ),
  },
]

/** Las columnas de cifras, para técnicos y categorías. */
const cifras = <T extends CifrasTickets>(): DataTableColumn<T>[] => [
  { accessor: 'abiertos', title: 'Recibidos', width: 100 },
  { accessor: 'resueltos', title: 'Resueltos', width: 100 },
  { accessor: 'cumplimiento_sla', title: 'En plazo', width: 100, render: (c) => numero(c.cumplimiento_sla, ' %') },
  { accessor: 'horas_resolucion', title: 'Tiempo', width: 100, render: (c) => numero(c.horas_resolucion, ' h') },
  { accessor: 'reabiertos', title: 'Reabiertos', width: 100 },
  { accessor: 'satisfaccion', title: 'Satisfacción', width: 120, render: (c) => (c.encuestas ? `${numero(c.satisfaccion)} (${c.encuestas})` : '—') },
]

export const columnasPorTecnico: DataTableColumn<CifrasTickets & { tecnico: string; en_curso: number }>[] = [
  { accessor: 'tecnico', title: 'Técnico' },
  ...cifras<CifrasTickets & { tecnico: string; en_curso: number }>(),
  { accessor: 'en_curso', title: 'En curso hoy', width: 120 },
]

export const columnasPorCategoria: DataTableColumn<CifrasTickets & { categoria: string }>[] = [
  { accessor: 'categoria', title: 'Categoría' },
  ...cifras<CifrasTickets & { categoria: string }>(),
]

type Comentario = ReporteSatisfaccion['comentarios'][number]

export const columnasComentarios: DataTableColumn<Comentario>[] = [
  {
    accessor: 'folio',
    title: 'Ticket',
    width: 150,
    render: (c) => (
      <Stack gap={0}>
        <Anchor size="sm" component={Link} href={ROUTES.TIC.TICKET(c.ticket_id)}>{c.folio ?? '—'}</Anchor>
        <Text size="xs" c="dimmed">{formatFechaHora(c.fecha)}</Text>
      </Stack>
    ),
  },
  { accessor: 'calificacion', title: 'Nota', width: 80, render: (c) => `${c.calificacion} / 5` },
  { accessor: 'tecnico', title: 'Técnico', width: 200, render: (c) => c.tecnico ?? '—' },
  { accessor: 'comentario', title: 'Comentario' },
]
