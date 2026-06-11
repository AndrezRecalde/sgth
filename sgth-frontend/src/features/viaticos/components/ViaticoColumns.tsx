'use client'

import { Text, Badge, Stack } from '@mantine/core'
import {
  IconPlane, IconCheck, IconCurrencyDollar,
} from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import { ESTADO_COLORS, ESTADO_LABELS } from '../constants/viatico.constants'
import type { EstadoViatico, ViaticoConRelaciones } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const ZONA_ABREV: Record<string, string> = {
  dentro_provincia: 'Dentro prov.',
  fuera_provincia:  'Fuera prov.',
  exterior:         'Exterior',
}

interface ColumnActions {
  onVer:    (v: ViaticoConRelaciones) => void
  onAprobar: (v: ViaticoConRelaciones) => void
  onLiquidar: (v: ViaticoConRelaciones) => void
}

export function getViaticoColumns(
  actions: ColumnActions
): DataTableColumn<ViaticoConRelaciones>[] {
  return [
    {
      accessor: 'codigo_viatico',
      title:    'Código',
      width:    150,
      render: ({ codigo_viatico }) => (
        <Text size="sm" ff="monospace" fw={500}>
          {codigo_viatico ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'servidor',
      title:    'Servidor',
      render: (v) => {
        const s = v.servidor
        if (!s) return <Text size="sm" c="dimmed">—</Text>
        return (
          <Text size="sm">
            {[s.apellido, s.nombre].filter(Boolean).join(' ')}
          </Text>
        )
      },
    },
    {
      accessor: 'zona',
      title:    'Zona',
      width:    130,
      render: ({ zona }) => (
        <Text size="sm">
          {ZONA_ABREV[zona as string] ?? zona}
        </Text>
      ),
    },
    {
      accessor: 'datetime_salida',
      title:    'Período',
      width:    160,
      render: ({ datetime_salida, datetime_llegada, total_dias }) => {
        if (!datetime_salida) {
          return (
            <Badge color="orange" variant="dot" size="sm">
              Sin itinerario
            </Badge>
          )
        }
        const fmt = (f: string) =>
          new Date(f.replace(/-/g, '/')).toLocaleDateString('es-EC', {
            day: '2-digit', month: '2-digit', year: '2-digit',
          })
        return (
          <Stack gap={0}>
            <Text size="xs" ff="monospace">
              {fmt(datetime_salida as string)} –{' '}
              {fmt(datetime_llegada as string)}
            </Text>
            <Text size="xs" c="dimmed">
              {Number(total_dias ?? 0).toFixed(1)} días
            </Text>
          </Stack>
        )
      },
    },
    {
      accessor: 'monto_calculado',
      title:    'Monto',
      width:    100,
      render: ({ monto_calculado }) => (
        <Text size="sm" ff="monospace" ta="right">
          ${Number(monto_calculado ?? 0).toFixed(2)}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    140,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado as EstadoViatico] ?? 'gray'}
          variant="light"
          size="sm"
        >
          {ESTADO_LABELS[estado as EstadoViatico] ?? estado}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (v) => (
        <TableActions
          actions={[
            {
              label:   'Ver detalle',
              icon:    <IconPlane size={14} />,
              color:   'blue',
              onClick: () => actions.onVer(v),
            },
            {
              label:   'Aprobar',
              icon:    <IconCheck size={14} />,
              color:   'emerald',
              onClick: () => actions.onAprobar(v),
              hidden:  (v.estado as string) !== 'solicitado',
            },
            {
              label:   'Liquidar',
              icon:    <IconCurrencyDollar size={14} />,
              color:   'orange',
              onClick: () => actions.onLiquidar(v),
              hidden:  (v.estado as string) !== 'pendiente_liquidacion',
            },
          ]}
        />
      ),
    },
  ]
}
