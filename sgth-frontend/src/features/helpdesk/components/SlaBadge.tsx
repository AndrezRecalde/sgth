import { Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import { SLA_LABELS } from '../constants/estados'
import type { Ticket } from '../services/tipos'

/** Cómo va el SLA del ticket y, si sigue en curso, cuándo vence. */
export function SlaBadge({ ticket, conVencimiento = true }: { ticket: Ticket; conVencimiento?: boolean }) {
  const estado = ticket.sla.estado
  if (!estado) return <Text size="xs" c="dimmed">—</Text>

  const { etiqueta, tono } = SLA_LABELS[estado]
  const enCurso = estado === 'en_plazo' || estado === 'por_vencer' || estado === 'vencido'

  return (
    <Stack gap={2}>
      <StatusBadge tone={tono} variant="dot">{etiqueta}</StatusBadge>
      {conVencimiento && enCurso && ticket.sla.vence_en && (
        <Text size="xs" c="dimmed">{estado === 'vencido' ? 'Venció' : 'Vence'} {formatFechaHora(ticket.sla.vence_en)}</Text>
      )}
    </Stack>
  )
}
