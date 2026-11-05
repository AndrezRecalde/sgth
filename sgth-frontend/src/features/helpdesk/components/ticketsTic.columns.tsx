import { Box, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { TONO_ESTADO_TICKET, TONO_PRIORIDAD } from '../constants/estados'
import type { Ticket } from '../services/tipos'
import { SlaBadge } from './SlaBadge'
import { celdaAsuntoTicket } from './tickets.columns'

const EN_CURSO = ['abierto', 'asignado', 'en_proceso', 'pendiente_usuario']

/**
 * Uno de hardware en curso que todavía no tiene equipo: al resolverlo se
 * pedirá el atendido, así que conviene identificarlo en el sitio (2026-11-05).
 */
const sinEquipo = (t: Ticket) =>
  Boolean(t.categoria?.es_hardware && !t.categoria?.es_consumibles && !t.bien && EN_CURSO.includes(t.estado))

/** La bandeja de TIC: quién pide, a qué área va, su prioridad, quién lo tiene y su SLA. */
export const columnasBandejaTickets: DataTableColumn<Ticket>[] = [
  {
    accessor: 'asunto',
    title: 'Ticket',
    render: (t) => (sinEquipo(t)
      ? <Stack gap={4} align="flex-start">{celdaAsuntoTicket(t)}<Box><StatusBadge tone="warning">Sin equipo</StatusBadge></Box></Stack>
      : celdaAsuntoTicket(t)),
  },
  {
    accessor: 'solicitante',
    title: 'Solicitante',
    width: 170,
    render: (t) => (
      <Stack gap={0}>
        <Text size="sm">{t.solicitante?.nombre}</Text>
        <Text size="xs" c="dimmed">{t.solicitante?.unidad ?? ''}{t.canal === 'tic' ? ' · registrado por TIC' : ''}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'prioridad',
    title: 'Prioridad',
    width: 100,
    render: (t) => <StatusBadge tone={TONO_PRIORIDAD[t.prioridad]}>{t.prioridad_etiqueta}</StatusBadge>,
  },
  {
    accessor: 'tecnico',
    title: 'Lo atiende',
    width: 160,
    render: (t) => (
      <Stack gap={0}>
        <Text size="sm">{t.tecnico?.nombre ?? 'Sin asignar'}</Text>
        <Text size="xs" c="dimmed">{t.unidad_tic?.nombre ?? ''}{t.nivel > 1 ? ` · nivel ${t.nivel}` : ''}</Text>
      </Stack>
    ),
  },
  { accessor: 'sla', title: 'SLA', width: 150, render: (t) => <SlaBadge ticket={t} /> },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 120,
    render: (t) => <StatusBadge tone={TONO_ESTADO_TICKET[t.estado]}>{t.estado_etiqueta}</StatusBadge>,
  },
]
