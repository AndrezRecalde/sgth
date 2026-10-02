'use client'

import { TableActions, type TableAction } from '@/components/ui'
import type { DataTableColumn } from 'mantine-datatable'
import type { HorasTrabajadasPeriodo } from '../services/tipos'

/**
 * Columnas de las horas trabajadas por período, el denominador de los índices
 * del CD 513.
 *
 * Las acciones llegan desde el modal, que es quien tiene las mutaciones: aquí
 * solo se dibuja el menú.
 */
export function columnasHorasTrabajadas(
  accionesDe: (registro: HorasTrabajadasPeriodo) => TableAction[],
): DataTableColumn<HorasTrabajadasPeriodo>[] {
  return [
    { accessor: 'periodo', title: 'Período', width: 110 },
    {
      accessor: 'unidad_administrativa',
      title: 'Unidad',
      render: (r) => r.unidad_administrativa?.nombre ?? 'Total institucional',
    },
    {
      accessor: 'total_horas',
      title: 'Horas',
      width: 110,
      render: (r) => r.total_horas.toLocaleString(),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (r) => <TableActions actions={accionesDe(r)} />,
    },
  ]
}
