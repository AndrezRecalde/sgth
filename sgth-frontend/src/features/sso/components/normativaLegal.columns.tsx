'use client'

import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { TIPO_NORMATIVA_OPTIONS } from '../schemas/normativaLegal.schema'
import type { DataTableColumn } from 'mantine-datatable'
import type { NormativaLegalSso } from '../services/tipos'

const etiquetaTipo = (valor: string) =>
  TIPO_NORMATIVA_OPTIONS.find((o) => o.value === valor)?.label ?? valor

/**
 * Columnas del catálogo de normativa legal de seguridad y salud.
 *
 * Las acciones llegan desde el modal, que es quien tiene las mutaciones: aquí
 * solo se dibuja el menú.
 */
export function columnasNormativaLegal(
  accionesDe: (normativa: NormativaLegalSso) => TableAction[],
): DataTableColumn<NormativaLegalSso>[] {
  return [
    { accessor: 'nombre', title: 'Normativa' },
    {
      accessor: 'tipo',
      title: 'Tipo',
      width: 160,
      render: (n) => <StatusBadge>{etiquetaTipo(n.tipo)}</StatusBadge>,
    },
    {
      accessor: 'activo',
      title: 'Estado',
      width: 100,
      render: (n) => (
        <StatusBadge tone={n.activo ? 'success' : 'neutral'}>
          {n.activo ? 'Activa' : 'Inactiva'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (n) => <TableActions actions={accionesDe(n)} />,
    },
  ]
}
