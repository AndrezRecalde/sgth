'use client'

import { Badge, Stack, Text } from '@mantine/core'
import { IconEye } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import { ESTADO_COLORS, ESTADO_LABELS } from '../../constants/viatico.constants'
import type { EtapaBandeja, ViaticoBandeja } from '@/types/api'

const monto = (v: number | string) => `$${Number(v ?? 0).toFixed(2)}`

/** Cuántos días hábiles quedan para liquidar, o si ya venció. */
function Plazo({ plazo }: { plazo: ViaticoBandeja['plazo'] }) {
  if (!plazo) return <Text size="sm" c="dimmed">—</Text>

  const dias = plazo.dias_habiles_restantes
  const color = plazo.vencida ? 'red' : dias <= 1 ? 'amber' : 'emerald'
  const texto = plazo.vencida
    ? 'Vencida'
    : dias === 0 ? 'Vence hoy' : `${dias} día${dias === 1 ? '' : 's'} hábil${dias === 1 ? '' : 'es'}`

  return (
    <Stack gap={2}>
      <Badge color={color} variant="light" size="sm">{texto}</Badge>
      <Text size="xs" c="dimmed">Hasta {formatFechaHora(plazo.fecha_limite)}</Text>
    </Stack>
  )
}

export function getBandejaColumns(
  etapa: EtapaBandeja,
  onVer: (v: ViaticoBandeja) => void,
): DataTableColumn<ViaticoBandeja>[] {
  return [
    {
      accessor: 'codigo_viatico',
      title:    'Código',
      width:    170,
      render:   (v) => <Text size="sm" ff="monospace" fw={500}>{v.codigo_viatico ?? '—'}</Text>,
    },
    {
      accessor: 'servidor',
      title:    'Servidor',
      render:   (v) => (
        <Stack gap={0}>
          <Text size="sm">{[v.servidor?.apellido, v.servidor?.nombre].filter(Boolean).join(' ') || '—'}</Text>
          <Text size="xs" c="dimmed">{v.servidor?.unidad ?? ''}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'datetime_salida',
      title:    'Salida – regreso',
      width:    200,
      render:   (v) => (
        <Text size="xs" ff="monospace">
          {formatFechaHora(v.datetime_salida, { conHora: false })} – {formatFechaHora(v.datetime_llegada, { conHora: false })}
        </Text>
      ),
    },
    {
      accessor: 'monto_calculado',
      title:    'Monto',
      width:    110,
      textAlign: 'right',
      render:   (v) => (
        <Stack gap={0} align="flex-end">
          <Text size="sm" ff="monospace">{monto(v.monto_calculado)}</Text>
          {Number(v.monto_anticipo) > 0 && (
            <Text size="xs" c="dimmed" ff="monospace">Anticipo {monto(v.monto_anticipo)}</Text>
          )}
        </Stack>
      ),
    },
    etapa === 'por_liquidar'
      ? {
          accessor: 'plazo',
          title:    'Plazo para liquidar',
          width:    190,
          render:   (v) => <Plazo plazo={v.plazo} />,
        }
      : {
          accessor: 'estado',
          title:    'Estado',
          width:    170,
          render:   (v) => (
            <Badge color={ESTADO_COLORS[v.estado] ?? 'gray'} variant="light" size="sm">
              {ESTADO_LABELS[v.estado] ?? v.estado}
            </Badge>
          ),
        },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render:   (v) => (
        <TableActions
          actions={[{ label: 'Abrir viático', icon: <IconEye size={14} />, onClick: () => onVer(v) }]}
        />
      ),
    },
  ]
}
