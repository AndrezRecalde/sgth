'use client'

import { Text } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'
import type { CapacitacionSso } from '../services/tipos'

/** Una hora y media se escribe «1,5 h», no «1.5». */
const horas = (valor: number) =>
  `${valor.toLocaleString('es-EC', { maximumFractionDigits: 2 })} h`

/**
 * Columnas del registro de capacitaciones en seguridad y salud.
 *
 * Las acciones llegan desde la vista, que es quien tiene las mutaciones, los
 * modales y el permiso: aquí solo se dibuja el menú.
 */
export function columnasCapacitacion(
  accionesDe: (capacitacion: CapacitacionSso) => TableAction[],
): DataTableColumn<CapacitacionSso>[] {
  return [
    {
      accessor: 'tema',
      title: 'Tema',
      render: (c) => <Text size="sm" fw={500}>{c.tema}</Text>,
    },
    {
      accessor: 'fecha',
      title: 'Fecha',
      width: 110,
      render: (c) => formatFecha(c.fecha),
    },
    {
      accessor: 'duracion_horas',
      title: 'Duración',
      width: 100,
      render: (c) => horas(Number(c.duracion_horas)),
    },
    { accessor: 'instructor', title: 'Instructor' },
    {
      accessor: 'lugar',
      title: 'Lugar',
      render: (c) => c.lugar ?? <Text size="sm" c="dimmed">—</Text>,
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 90,
      render: (c) => (
        <StatusBadge tone={c.estado ? 'success' : 'neutral'}>
          {c.estado ? 'Activa' : 'Inactiva'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (c) => <TableActions actions={accionesDe(c)} />,
    },
  ]
}
