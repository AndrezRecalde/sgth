'use client'

import { Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import type { Nomina, RolPago } from '@/types/api'

type RolDeNomina = NonNullable<Nomina['roles_pago']>[number]
type DetalleDeRol = NonNullable<NonNullable<RolPago['nomina']>['detalles']>[number]

export function formatMonto(v?: number | string | null): string {
  if (v === null || v === undefined) return '—'
  return `$${Number(v).toLocaleString('es-EC', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

/** Los roles de pago de una nómina, en su detalle. */
export const columnasRolesPago: DataTableColumn<RolDeNomina>[] = [
  {
    accessor: 'servidor.cedula',
    title: 'Cédula',
    width: 120,
    render: (r) => <Text size="sm" ff="monospace">{r.servidor?.cedula ?? '—'}</Text>,
  },
  {
    accessor: 'servidor.apellido',
    title: 'Servidor',
    render: (r) => (
      <Text size="sm">
        {[r.servidor?.apellido, r.servidor?.nombre].filter(Boolean).join(' ') || '—'}
      </Text>
    ),
  },
  {
    accessor: 'total_ingresos',
    title: 'Ingresos',
    textAlign: 'right',
    render: (r) => <Text size="sm" ff="monospace" c="emerald">{formatMonto(r.total_ingresos)}</Text>,
  },
  {
    accessor: 'total_descuentos',
    title: 'Descuentos',
    textAlign: 'right',
    render: (r) => <Text size="sm" ff="monospace" c="red">{formatMonto(r.total_descuentos)}</Text>,
  },
  {
    accessor: 'total_neto',
    title: 'Neto',
    textAlign: 'right',
    render: (r) => <Text size="sm" ff="monospace" fw={600}>{formatMonto(r.total_neto)}</Text>,
  },
]

/**
 * Los conceptos de un rol de pago. Ingresos y descuentos usan las mismas
 * columnas; solo cambia el color del valor.
 */
export function getConceptosColumns(tipo: 'ingreso' | 'descuento'): DataTableColumn<DetalleDeRol>[] {
  return [
    {
      accessor: 'concepto.nombre',
      title: 'Concepto',
      render: (d) => <Text size="sm">{d.concepto?.nombre ?? '—'}</Text>,
    },
    {
      accessor: 'valor',
      title: 'Valor',
      textAlign: 'right',
      width: 140,
      render: (d) => (
        <Text size="sm" ff="monospace" c={tipo === 'ingreso' ? 'emerald' : 'red'}>
          {formatMonto(d.valor)}
        </Text>
      ),
    },
  ]
}
