'use client'

import { Stack, Text } from '@mantine/core'
import { IconEye } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import { columnaCodigo, columnaEstado, columnaMonto, columnaPeriodo } from '../viatico.columns'
import type { SemanticTone } from '@/config/design.tokens'
import type { EtapaBandeja, ViaticoBandeja } from '@/types/api'

/** Cuántos días hábiles quedan para liquidar, o si ya venció. */
function Plazo({ plazo }: { plazo: ViaticoBandeja['plazo'] }) {
  if (!plazo) return <Text size="sm" c="dimmed">—</Text>

  const dias = plazo.dias_habiles_restantes
  const tone: SemanticTone = plazo.vencida ? 'danger' : dias <= 1 ? 'warning' : 'success'
  const texto = plazo.vencida
    ? 'Vencida'
    : dias === 0 ? 'Vence hoy' : `${dias} día${dias === 1 ? '' : 's'} hábil${dias === 1 ? '' : 'es'}`

  return (
    <Stack gap={2}>
      <StatusBadge tone={tone}>{texto}</StatusBadge>
      <Text size="xs" c="dimmed">Hasta {formatFechaHora(plazo.fecha_limite)}</Text>
    </Stack>
  )
}

/** Las columnas de una pestaña: las de todo viático, con quién viaja y, al liquidar, el plazo. */
export function getBandejaColumns(
  etapa: EtapaBandeja,
  onVer: (v: ViaticoBandeja) => void,
): DataTableColumn<ViaticoBandeja>[] {
  return [
    columnaCodigo(),
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
    columnaPeriodo(),
    columnaMonto(),
    etapa === 'por_liquidar'
      ? {
          accessor: 'plazo',
          title:    'Plazo para liquidar',
          width:    190,
          render:   (v) => <Plazo plazo={v.plazo} />,
        }
      : columnaEstado(),
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
