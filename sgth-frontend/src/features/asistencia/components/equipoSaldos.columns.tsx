'use client'

import { Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import type { SaldoDelEquipo } from '../services/asistenciaService'
import { diasDeSaldo } from '../utils/diasDeSaldo'

export function getEquipoSaldosColumns(): DataTableColumn<SaldoDelEquipo>[] {
  return [
    {
      accessor: 'apellido',
      title: 'Servidor',
      render: ({ apellido, nombre }) => (
        <Text size="sm">{[apellido, nombre].filter(Boolean).join(' ') || '—'}</Text>
      ),
    },
    {
      accessor: 'unidad',
      title: 'Unidad',
      render: ({ unidad }) => (
        <Text size="sm" c="dimmed">{unidad ?? '—'}</Text>
      ),
    },
    {
      accessor: 'saldo_dias',
      title: 'Saldo de vacaciones',
      textAlign: 'right',
      width: 180,
      render: ({ saldo_dias }) => saldo_dias === null
        ? <Text size="sm" c="dimmed">No genera vacaciones</Text>
        : <Text size="sm" ff="monospace">{diasDeSaldo(saldo_dias)}</Text>,
    },
  ]
}
