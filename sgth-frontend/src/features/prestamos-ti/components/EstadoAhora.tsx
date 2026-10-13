import { Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import type { EstadoAhora as Ahora } from '../services/tipos'
import { cuandoCorto } from '../utils/agenda'

/**
 * Cómo está el equipo en este momento. En el Portal no se dice quién lo tiene
 * (D15); si el dato llega —en TIC—, se muestra debajo.
 */
export function EstadoAhora({ ahora }: { ahora: Ahora }) {
  switch (ahora.estado) {
    case 'disponible':
      return (
        <Stack gap={2}>
          <StatusBadge tone="success">Disponible</StatusBadge>
          {ahora.proxima_reserva && <Text size="xs" c="dimmed">Reservado desde {cuandoCorto(ahora.proxima_reserva)}</Text>}
        </Stack>
      )
    case 'prestado':
      return (
        <Stack gap={2}>
          <StatusBadge tone={ahora.vencido ? 'danger' : 'info'}>{ahora.vencido ? 'Vencido' : 'Prestado'}</StatusBadge>
          <Text size="xs" c="dimmed">
            {ahora.vencido ? `Debía volver ${cuandoCorto(ahora.hasta)}` : `Hasta ${cuandoCorto(ahora.hasta)}`}
            {ahora.servidor ? ` · ${ahora.servidor}` : ''}
          </Text>
        </Stack>
      )
    case 'reservado':
      return (
        <Stack gap={2}>
          <StatusBadge tone="warning">Reservado</StatusBadge>
          <Text size="xs" c="dimmed">Hasta {cuandoCorto(ahora.hasta)}{ahora.servidor ? ` · ${ahora.servidor}` : ''}</Text>
        </Stack>
      )
    case 'no_disponible':
      return <StatusBadge tone="neutral">{ahora.detalle}</StatusBadge>
  }
}
