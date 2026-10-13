import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import { ESTADO_PARA_EL_SOLICITANTE, TONO_ESTADO_TICKET } from '../constants/estados'
import type { Ticket } from '../services/tipos'

export const celdaAsuntoTicket = (t: Ticket) => (
  <Stack gap={0}>
    <Text size="sm" fw={600} lineClamp={1}>{t.asunto}</Text>
    <Text size="xs" c="dimmed">
      {t.folio} · {t.categoria?.nombre}{t.bien ? ` · ${t.bien.codigo_institucional}` : ''}
    </Text>
  </Stack>
)

/** «Mis tickets» del Portal: el estado, dicho desde quien lo abrió. */
export const columnasMisTickets: DataTableColumn<Ticket>[] = [
  { accessor: 'asunto', title: 'Ticket', render: celdaAsuntoTicket },
  { accessor: 'created_at', title: 'Abierto', width: 150, render: (t) => formatFechaHora(t.created_at) },
  { accessor: 'tecnico', title: 'Lo atiende', width: 180, render: (t) => t.tecnico?.nombre ?? '—' },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 190,
    render: (t) => <StatusBadge tone={TONO_ESTADO_TICKET[t.estado]}>{ESTADO_PARA_EL_SOLICITANTE[t.estado]}</StatusBadge>,
  },
]
