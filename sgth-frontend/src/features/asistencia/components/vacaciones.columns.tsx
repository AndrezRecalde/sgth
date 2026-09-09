'use client'

import { Text } from '@mantine/core'
import { IconCheck, IconPrinter, IconX } from '@tabler/icons-react'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import { ESTADO_LABELS, MOTIVO_LABELS, TONO_ESTADO } from './vacaciones.constants'
import type { DataTableColumn } from 'mantine-datatable'
import type { Vacacion } from '@/types/api'

interface ColumnActions {
  exportandoId: number | null
  onExportar:   (id: number) => void
  onAprobar:    (id: number) => void
  onRechazar:   (id: number) => void
}

/** Las fechas vienen como `date` sin hora: se leen en UTC o se corren un día. */
function fecha(valor: string | null | undefined): string {
  if (!valor) return '—'

  return new Date(valor).toLocaleDateString('es-EC', {
    timeZone: 'UTC',
    day:      '2-digit',
    month:    '2-digit',
    year:     'numeric',
  })
}

export function getVacacionesColumns(
  actions: ColumnActions
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
      accessor: 'servidor',
      title: 'Servidor',
      render: ({ servidor }) =>
        servidor ? (
          <Text size="sm">
            {[servidor.apellido, servidor.nombre].filter(Boolean).join(' ')}
          </Text>
        ) : (
          <Text size="sm" c="dimmed">—</Text>
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
      render: ({ fecha_inicio }) => <Text size="sm">{fecha(fecha_inicio)}</Text>,
    },
    {
      accessor: 'fecha_fin',
      title: 'Hasta',
      width: 110,
      render: ({ fecha_fin }) => <Text size="sm">{fecha(fecha_fin)}</Text>,
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
      width: 110,
      render: ({ estado }) => (
        <StatusBadge tone={TONO_ESTADO[estado] ?? 'neutral'}>
          {ESTADO_LABELS[estado] ?? estado}
        </StatusBadge>
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
              label: actions.exportandoId === v.id
                ? 'Exportando...'
                : 'Imprimir solicitud',
              icon: <IconPrinter size={14} />,
              color: 'blue',
              onClick: () => actions.onExportar(v.id),
            },
            {
              label: 'Aprobar',
              icon: <IconCheck size={14} />,
              color: 'emerald',
              onClick: () => actions.onAprobar(v.id),
              hidden: v.estado !== 'pendiente',
            },
            {
              label: 'Rechazar',
              icon: <IconX size={14} />,
              color: 'red',
              onClick: () =>
                confirmar({
                  title: 'Rechazar solicitud',
                  message:
                    'Se rechazará esta solicitud de vacaciones y el servidor será notificado.',
                  destructiva: true,
                  confirmLabel: 'Rechazar',
                  onConfirm: () => actions.onRechazar(v.id),
                }),
              hidden: v.estado !== 'pendiente',
            },
          ]}
        />
      ),
    },
  ]
}
