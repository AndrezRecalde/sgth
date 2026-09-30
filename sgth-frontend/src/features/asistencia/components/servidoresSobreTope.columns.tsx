'use client'

import { Stack, Text } from '@mantine/core'
import { IconHourglassEmpty } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import { REGIMEN_LABELS } from '@/lib/regimen'
import type { DataTableColumn } from 'mantine-datatable'
import type { ServidorSobreTope } from '@/types/api'

interface ColumnActions {
  /** `gestionar-vacaciones`: sin él, vencer el excedente no se ofrece. */
  puedeVencer: boolean
  onVencer: (fila: ServidorSobreTope) => void
}

const dias = (n: number) => n.toFixed(2)

export function getServidoresSobreTopeColumns(
  { puedeVencer, onVencer }: ColumnActions
): DataTableColumn<ServidorSobreTope>[] {
  return [
    {
      accessor: 'nombre',
      title: 'Servidor',
      render: ({ nombre, cedula, unidad }) => (
        <Stack gap={0}>
          <Text size="sm">{nombre}</Text>
          <Text size="xs" c="dimmed">
            {cedula}
            {unidad ? ` · ${unidad}` : ''}
          </Text>
        </Stack>
      ),
    },
    {
      accessor: 'regimen',
      title: 'Régimen',
      width: 150,
      render: ({ regimen }) => (
        <StatusBadge>{REGIMEN_LABELS[regimen] ?? regimen}</StatusBadge>
      ),
    },
    {
      accessor: 'saldo',
      title: 'Saldo',
      width: 100,
      render: ({ saldo }) => <Text size="sm">{dias(saldo)}</Text>,
    },
    {
      accessor: 'tope',
      title: 'Tope',
      width: 90,
      render: ({ tope }) => <Text size="sm">{dias(tope)}</Text>,
    },
    {
      accessor: 'excedente',
      title: 'Excedente',
      width: 110,
      render: ({ excedente }) => (
        <Text size="sm" fw={600} c={excedente > 0 ? 'red' : 'dimmed'}>
          {excedente > 0 ? dias(excedente) : '—'}
        </Text>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (fila) => (
        <TableActions
          actions={[
            {
              label: 'Vencer excedente',
              icon: <IconHourglassEmpty size={14} />,
              color: 'red',
              hidden: !puedeVencer || fila.excedente <= 0,
              onClick: () => onVencer(fila),
            },
          ]}
        />
      ),
    },
  ]
}
