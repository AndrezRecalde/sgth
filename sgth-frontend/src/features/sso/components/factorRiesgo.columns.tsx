'use client'

import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { CATEGORIA_FACTOR_OPTIONS } from '../schemas/factorRiesgo.schema'
import type { DataTableColumn } from 'mantine-datatable'
import type { FactorRiesgoCatalogo } from '../services/tipos'

const etiquetaCategoria = (valor: string) =>
  CATEGORIA_FACTOR_OPTIONS.find((o) => o.value === valor)?.label ?? valor

/**
 * Columnas del catálogo de factores de riesgo.
 *
 * Las acciones llegan desde el modal, que es quien tiene las mutaciones: aquí
 * solo se dibuja el menú.
 */
export function columnasFactorRiesgo(
  accionesDe: (factor: FactorRiesgoCatalogo) => TableAction[],
): DataTableColumn<FactorRiesgoCatalogo>[] {
  return [
    { accessor: 'nombre', title: 'Factor' },
    {
      accessor: 'categoria',
      title: 'Categoría',
      width: 160,
      render: (f) => <StatusBadge>{etiquetaCategoria(f.categoria)}</StatusBadge>,
    },
    {
      accessor: 'activo',
      title: 'Estado',
      width: 100,
      render: (f) => (
        <StatusBadge tone={f.activo ? 'success' : 'neutral'}>
          {f.activo ? 'Activo' : 'Inactivo'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (f) => <TableActions actions={accionesDe(f)} />,
    },
  ]
}
