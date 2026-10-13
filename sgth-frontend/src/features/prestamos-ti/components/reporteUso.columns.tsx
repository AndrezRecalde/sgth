import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import type { FilaUsoEquipo } from '../services/tipos'
import { momentoCorto } from '../utils/agenda'

const numero = (n: number) => (n > 0 ? n.toLocaleString('es-EC') : '—')

/** El uso de cada equipo de préstamo en el rango. */
export const columnasReporteUso: DataTableColumn<FilaUsoEquipo>[] = [
  {
    accessor: 'codigo_institucional',
    title: 'Equipo',
    render: (e) => (
      <Stack gap={0}>
        <Text size="sm" fw={600}>{e.codigo_institucional}</Text>
        <Text size="xs" c="dimmed">{[e.tipo, e.marca, e.modelo].filter(Boolean).join(' · ')}</Text>
        {!e.prestable && <StatusBadge tone="neutral" mt={2}>Ya no se presta</StatusBadge>}
      </Stack>
    ),
  },
  { accessor: 'prestamos', title: 'Préstamos', width: 110, textAlign: 'right', render: (e) => numero(e.prestamos) },
  { accessor: 'horas', title: 'Horas fuera', width: 120, textAlign: 'right', render: (e) => numero(e.horas) },
  {
    accessor: 'devoluciones_tarde', title: 'Devueltos tarde', width: 140, textAlign: 'right',
    render: (e) => (e.devoluciones_tarde > 0 ? <Text size="sm" c="red">{e.devoluciones_tarde}</Text> : '—'),
  },
  { accessor: 'no_retirados', title: 'No retirados', width: 120, textAlign: 'right', render: (e) => numero(e.no_retirados) },
  { accessor: 'ultimo_prestamo', title: 'Último préstamo', width: 160, render: (e) => momentoCorto(e.ultimo_prestamo) },
]
