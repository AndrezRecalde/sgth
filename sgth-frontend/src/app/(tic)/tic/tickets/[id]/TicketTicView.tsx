'use client'

import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Anchor, Grid, Group, Skeleton, Stack, Text, Timeline } from '@mantine/core'
import { IconHeadset } from '@tabler/icons-react'
import { DetailList, EmptyState, PageHeader, PageShell, SectionCard, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { formatFechaHora } from '@/lib/fecha'
import { plural } from '@/lib/plural'
import { SolicitudesDelTicket } from '@/features/consumibles-ti/components/SolicitudesDelTicket'
import { AccionesTicketTic } from '@/features/helpdesk/components/AccionesTicketTic'
import { ConversacionTicket } from '@/features/helpdesk/components/ConversacionTicket'
import { SlaBadge } from '@/features/helpdesk/components/SlaBadge'
import { TONO_ESTADO_TICKET, TONO_PRIORIDAD } from '@/features/helpdesk/constants/estados'
import { useTicket } from '@/features/helpdesk/hooks/useTickets'

/**
 * Un ticket visto por TIC: la conversación con las notas internas, quién lo
 * pidió y quién lo atiende, el SLA, el equipo y el historial completo, con el
 * paso que toca.
 */
export function TicketTicView({ id }: { id: number }) {
  const router = useRouter()
  const { usuario, hasPermiso } = useAuth()
  // La nota interna es de quien atiende (`TicketPolicy::verNotasInternas`). A
  // quien solo consulta la Bandeja, el API se la guarda como mensaje público,
  // a la vista del solicitante, así que el selector no se le ofrece (PR 14).
  const conNotasInternas = hasPermiso('gestionar-tickets')
  const { data: t, isLoading } = useTicket(id)

  if (isLoading) {
    return <PageShell><Skeleton height={80} radius="lg" /><Skeleton height={360} radius="lg" /></PageShell>
  }

  if (!t) {
    return (
      <PageShell>
        <PageHeader title="Ticket" backHref={ROUTES.TIC.TICKETS} />
        <EmptyState icon={IconHeadset} title="Ticket no encontrado" description="No existe o no está en su cola. Vuelva a la bandeja." />
      </PageShell>
    )
  }

  const final = t.estado === 'cerrado' || t.estado === 'anulado'

  return (
    <PageShell>
      <PageHeader
        title={t.asunto}
        description={`${t.folio} · ${t.categoria?.nombre ?? ''}`}
        estado={(
          <Group gap={6}>
            <StatusBadge tone={TONO_ESTADO_TICKET[t.estado]}>{t.estado_etiqueta}</StatusBadge>
            <StatusBadge tone={TONO_PRIORIDAD[t.prioridad]} variant="outline">{t.prioridad_etiqueta}</StatusBadge>
          </Group>
        )}
        onBack={() => router.back()}
        actions={<AccionesTicketTic ticket={t} />}
      />

      <Grid>
        <Grid.Col span={{ base: 12, md: 8 }}>
          <Stack gap="md">
            {t.solucion && (
              <SectionCard title="Solución">
                <Text size="sm" style={{ whiteSpace: 'pre-line' }}>{t.solucion}</Text>
                <Text size="xs" c="dimmed" mt={4}>{t.resuelto_por} · {formatFechaHora(t.resuelto_en)}</Text>
              </SectionCard>
            )}
            {t.categoria?.es_consumibles && (
              <SolicitudesDelTicket final={final} ticket={{
                id: t.id,
                folio: t.folio,
                solicitante: t.solicitante?.nombre ?? null,
                equipo: t.bien ? { id: t.bien.id, texto: [t.bien.codigo_institucional, t.bien.tipo, t.bien.modelo].filter(Boolean).join(' · ') } : null,
              }} />
            )}
            <SectionCard title="Conversación">
              <Stack gap="md">
                <div>
                  <Text size="xs" c="dimmed">Lo que contó {t.solicitante?.nombre}, el {formatFechaHora(t.created_at)}</Text>
                  <Text size="sm" style={{ whiteSpace: 'pre-line' }}>{t.descripcion}</Text>
                </div>
                <ConversacionTicket ticket={t} usuarioId={usuario?.id ?? null} puedeEscribir={!final} conNotasInternas={conNotasInternas} />
              </Stack>
            </SectionCard>
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <Stack gap="md">
            <SectionCard title="Datos">
              <DetailList columnas={1} items={[
                { label: 'Solicitante', value: `${t.solicitante?.nombre ?? '—'}${t.solicitante?.unidad ? ` · ${t.solicitante.unidad}` : ''}` },
                { label: 'Lo atiende', value: `${t.tecnico?.nombre ?? 'Sin asignar'} · nivel ${t.nivel}` },
                { label: 'Área de TIC', value: t.unidad_tic?.nombre ?? '—' },
                {
                  label: 'Equipo',
                  value: t.bien
                    ? <Anchor size="sm" component={Link} href={ROUTES.TIC.EQUIPO(t.bien.id)}>{t.bien.codigo_institucional} · {[t.bien.tipo, t.bien.modelo].filter(Boolean).join(' ')}</Anchor>
                    : '—',
                },
                { label: 'Canal', value: t.canal === 'tic' ? `Registrado por TIC (${t.registrado_por ?? ''})` : 'Portal' },
                ...(t.reabierto_veces > 0 ? [{ label: 'Reabierto', value: plural(t.reabierto_veces, 'vez', 'veces') }] : []),
                ...(t.encuesta ? [{ label: 'Calificación', value: `${t.encuesta.calificacion} de 5${t.encuesta.comentarios ? ` · ${t.encuesta.comentarios}` : ''}` }] : []),
                ...(t.motivo_anulacion ? [{ label: 'Anulado', value: `${t.motivo_anulacion} (${t.anulado_por ?? ''})` }] : []),
              ]} />
            </SectionCard>
            <SectionCard title="SLA">
              <Stack gap="xs">
                <SlaBadge ticket={t} conVencimiento={false} />
                <DetailList columnas={1} items={[
                  { label: 'Primera respuesta', value: t.sla.primera_respuesta_en ? formatFechaHora(t.sla.primera_respuesta_en) : `Vence ${formatFechaHora(t.sla.vence_respuesta_en)}` },
                  { label: 'Resolución', value: `${t.sla.resolucion_horas ?? '—'} h hábiles · vence ${formatFechaHora(t.sla.vence_en)}` },
                  ...(t.sla.pausado_desde ? [{ label: 'En pausa desde', value: formatFechaHora(t.sla.pausado_desde) }] : []),
                ]} />
              </Stack>
            </SectionCard>
            <SectionCard title="Historial">
              <Timeline bulletSize={12} lineWidth={2} active={(t.historial ?? []).length - 1}>
                {(t.historial ?? []).map((h, n) => (
                  <Timeline.Item key={n} title={h.etiqueta}>
                    <Text size="xs" c="dimmed">{formatFechaHora(h.fecha)}{h.usuario ? ` · ${h.usuario}` : ''}</Text>
                    {h.detalle && <Text size="xs">{h.detalle}</Text>}
                  </Timeline.Item>
                ))}
              </Timeline>
            </SectionCard>
          </Stack>
        </Grid.Col>
      </Grid>
    </PageShell>
  )
}
