import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions, type TableAction } from '@/components/ui'
import type { Mantenimiento } from '../services/tipos'
import {
  celdaDescripcionMantenimiento, celdaFechaMantenimiento, celdaQuienMantenimiento, celdaTipoMantenimiento,
} from './mantenimientoCeldas'

/** La lista general de mantenimientos: la de la ficha, con el equipo y el costo. */
export const columnasMantenimientos = (accionesDe: (m: Mantenimiento) => TableAction[]): DataTableColumn<Mantenimiento>[] => [
  { accessor: 'fecha_mantenimiento', title: 'Fecha', width: 110, render: celdaFechaMantenimiento },
  {
    accessor: 'bien',
    title: 'Equipo',
    width: 190,
    render: (m) => (
      <Stack gap={0}>
        <Text size="sm" fw={600}>{m.bien?.codigo_institucional}</Text>
        <Text size="xs" c="dimmed">{[m.bien?.tipo, m.bien?.marca, m.bien?.modelo].filter(Boolean).join(' · ')}</Text>
      </Stack>
    ),
  },
  { accessor: 'tipo_mantenimiento', title: 'Tipo', width: 140, render: celdaTipoMantenimiento },
  { accessor: 'descripcion', title: 'Qué se hizo', render: celdaDescripcionMantenimiento },
  { accessor: 'tecnico', title: 'Quién', width: 170, render: celdaQuienMantenimiento },
  { accessor: 'costo', title: 'Costo', width: 90, textAlign: 'right', render: (m) => (m.costo > 0 ? `$${m.costo.toFixed(2)}` : '—') },
  { accessor: 'acciones', title: '', width: 50, render: (m) => <TableActions actions={accionesDe(m)} /> },
]
