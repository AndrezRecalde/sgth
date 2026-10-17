'use client'

import { Stack, Text } from '@mantine/core'
import { IconPrinter } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_LABELS, MOTIVO_LABELS, TONO_ESTADO } from './vacaciones.constants'
import type { DataTableColumn } from 'mantine-datatable'
import type { Vacacion } from '@/types/api'

interface ColumnActions {
  exportandoId: number | null
  onExportar:   (vacacion: Vacacion) => void
}

/**
 * Las solicitudes propias en «Mis vacaciones». Las de Talento Humano llevan
 * además al servidor y las acciones de resolver, que aquí no aplican: quien
 * aprueba y anula es Talento Humano (`aprobar-vacaciones`).
 */
export function getMisVacacionesColumns(
  { exportandoId, onExportar }: ColumnActions,
): DataTableColumn<Vacacion>[] {
  return [
    {
      accessor: 'folio',
      title: 'Folio',
      width: 145,
      render: ({ folio }) => (
        <Text size="sm" ff="monospace" fw={500}>{folio ?? '—'}</Text>
      ),
    },
    {
      accessor: 'motivo',
      title: 'Motivo',
      render: ({ motivo }) => (
        <Text size="sm">{MOTIVO_LABELS[motivo] ?? motivo}</Text>
      ),
    },
    {
      accessor: 'fecha_inicio',
      title: 'Desde',
      width: 110,
      render: ({ fecha_inicio }) => <Text size="sm">{formatFecha(fecha_inicio)}</Text>,
    },
    {
      accessor: 'fecha_fin',
      title: 'Hasta',
      width: 110,
      render: ({ fecha_fin }) => <Text size="sm">{formatFecha(fecha_fin)}</Text>,
    },
    {
      accessor: 'dias_solicitados',
      title: 'Días',
      width: 70,
      render: ({ dias_solicitados }) => (
        <Text size="sm" ta="center">{dias_solicitados}</Text>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 180,
      // El motivo de la anulación, debajo: es lo primero que se pregunta quien
      // ve su solicitud anulada.
      render: ({ estado, motivo_anulacion }) => (
        <Stack gap={2}>
          <StatusBadge tone={TONO_ESTADO[estado] ?? 'neutral'}>
            {ESTADO_LABELS[estado] ?? estado}
          </StatusBadge>
          {estado === 'anulada' && motivo_anulacion && (
            <Text size="xs" c="dimmed" lineClamp={2} title={motivo_anulacion}>
              {motivo_anulacion}
            </Text>
          )}
        </Stack>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (v) => (
        <TableActions
          actions={[
            {
              label: exportandoId === v.id ? 'Exportando...' : 'Imprimir solicitud',
              icon: <IconPrinter size={14} />,
              onClick: () => onExportar(v),
            },
          ]}
        />
      ),
    },
  ]
}
