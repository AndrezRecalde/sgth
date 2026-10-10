'use client'

import { Group, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { MovimientoPersonal } from '@/types/api'
import { ESTADO_LABELS, TONO_ACCION } from '../utils/estadoAccionPersonal'

/**
 * El estado de una acción y, si está registrada pero rige más tarde, desde
 * cuándo (fase 1.6). «Pendiente de vigencia» no es un estado: la acción ya es
 * un acto, pero el vínculo no cambia hasta su fecha.
 */
export function EstadoAccionBadge({
  m,
}: {
  m: Pick<MovimientoPersonal, 'estado' | 'pendiente_de_vigencia' | 'fecha_efectiva'>
}) {
  if (!m.estado) return <Text size="sm" c="dimmed">—</Text>

  return (
    <Group gap={4} wrap="wrap">
      <StatusBadge tone={TONO_ACCION[m.estado]}>{ESTADO_LABELS[m.estado]}</StatusBadge>
      {m.pendiente_de_vigencia && (
        <StatusBadge tone="info">Rige el {formatFecha(m.fecha_efectiva)}</StatusBadge>
      )}
    </Group>
  )
}
