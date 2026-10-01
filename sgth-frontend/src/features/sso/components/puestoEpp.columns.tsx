'use client'

import { TableActions, type TableAction } from '@/components/ui'
import type { DataTableColumn } from 'mantine-datatable'
import type { PuestoEpp } from '../services/tipos'

/**
 * Columnas del EPP que requiere un puesto.
 *
 * Las acciones llegan desde el modal, que es quien tiene las mutaciones: aquí
 * solo se dibuja el menú.
 */
export function columnasPuestoEpp(
  accionesDe: (asignacion: PuestoEpp) => TableAction[],
): DataTableColumn<PuestoEpp>[] {
  return [
    {
      accessor: 'equipo_proteccion',
      title: 'Equipo',
      render: (a) => a.equipo_proteccion?.nombre ?? `Equipo ${a.equipo_proteccion_id}`,
    },
    { accessor: 'cantidad_requerida', title: 'Cantidad' },
    {
      accessor: 'frecuencia_reposicion_meses',
      title: 'Reposición',
      render: (a) => a.frecuencia_reposicion_meses ? `Cada ${a.frecuencia_reposicion_meses} meses` : '—',
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (a) => <TableActions actions={accionesDe(a)} />,
    },
  ]
}
