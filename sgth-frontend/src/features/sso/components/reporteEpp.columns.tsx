'use client'

import { CountBadge } from '@/components/ui'
import type { DataTableColumn } from 'mantine-datatable'
import type { ReporteEppFila } from '../services/tipos'

/** Columnas del consolidado de movimientos de EPP por servidor. */
export const columnasReporteEpp: DataTableColumn<ReporteEppFila>[] = [
  { accessor: 'servidor_nombre', title: 'Servidor' },
  { accessor: 'puesto', title: 'Puesto' },
  {
    accessor: 'total_entregas',
    title: 'Entregas',
    render: (f) => <CountBadge>{f.total_entregas}</CountBadge>,
  },
  {
    accessor: 'total_devoluciones',
    title: 'Devoluciones',
    render: (f) => <CountBadge>{f.total_devoluciones}</CountBadge>,
  },
  {
    accessor: 'total_reposiciones',
    title: 'Reposiciones',
    render: (f) => <CountBadge>{f.total_reposiciones}</CountBadge>,
  },
]
