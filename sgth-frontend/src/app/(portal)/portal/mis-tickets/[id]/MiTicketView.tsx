'use client'

import { useState } from 'react'
import { Alert, Button, Grid, Group, Skeleton, Stack, Text, Timeline } from '@mantine/core'
import { IconCircleCheck, IconHeadset, IconRotate } from '@tabler/icons-react'
import { DetailList, EmptyState, MotivoModal, PageHeader, PageShell, SectionCard, StatusBadge } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { formatFechaHora } from '@/lib/fecha'
import { ConfirmarTicketModal } from '@/features/helpdesk/components/ConfirmarTicketModal'
import { ConversacionTicket } from '@/features/helpdesk/components/ConversacionTicket'
import { ESTADO_PARA_EL_SOLICITANTE, TONO_ESTADO_TICKET } from '@/features/helpdesk/constants/estados'
import { useTicket, useTicketSolicitanteMutations } from '@/features/helpdesk/hooks/useTickets'

/**
 * Un ticket desde quien lo abrió: la conversación con TIC, lo que se le pide
 * —responder, confirmar la solución— y el seguimiento.
 */
export function MiTicketView({ id }: { id: number }) {
  const { usuario } = useAuth()
  const { data: t, isLoading } = useTicket(id)
  const { reabrir, anular } = useTicketSolicitanteMutations()
  const [confirmando, setConfirmando] = useState(false)
  const [motivo, setMotivo] = useState<'reabrir' | 'anular' | null>(null)

  if (isLoading) {
    return <PageShell><Skeleton height={80} radius="lg" /><Skeleton height={360} radius="lg" /></PageShell>
  }

  if (!t) {
    return (
      <PageShell>
        <PageHeader title="Ticket" backHref={ROUTES.PORTAL.MIS_TICKETS} />
        <EmptyState icon={IconHeadset} title="Ticket no encontrado" description="Vuelva a «Mis tickets» y búsquelo allí." />
      </PageShell>
    )
  }

  const final = t.estado === 'cerrado' || t.estado === 'anulado'
  const mutacion = motivo === 'reabrir' ? reabrir : anular

  return (
    <PageShell>
      <PageHeader
        title={t.asunto}
        description={`${t.folio} · ${t.categoria?.nombre ?? ''}`}
        estado={<StatusBadge tone={TONO_ESTADO_TICKET[t.estado]}>{ESTADO_PARA_EL_SOLICITANTE[t.estado]}</StatusBadge>}
        backHref={ROUTES.PORTAL.MIS_TICKETS}
        actions={t.estado === 'abierto' ? (
          <Button variant="subtle" color="red" onClick={() => setMotivo('anular')}>Ya no lo necesito</Button>
        ) : undefined}
      />

      {t.estado === 'pendiente_usuario' && (
        <Alert color={SEMANTIC_COLOR.warning} variant="light">TIC le hizo una pregunta: respóndala en la conversación para que sigan con su ticket.</Alert>
      )}
      {t.estado === 'resuelto' && (
        <Alert color={SEMANTIC_COLOR.success} variant="light" title="TIC lo dio por resuelto">
          <Stack gap="xs">
            <Text size="sm" style={{ whiteSpace: 'pre-line' }}>{t.solucion}</Text>
            <Group gap="xs">
              <Button size="xs" leftSection={<IconCircleCheck size={14} />} onClick={() => setConfirmando(true)}>Confirmar la solución</Button>
              <Button size="xs" variant="subtle" leftSection={<IconRotate size={14} />} onClick={() => setMotivo('reabrir')}>Sigue sin funcionar</Button>
            </Group>
            <Text size="xs" c="dimmed">Si no responde, se cerrará solo en unos días.</Text>
          </Stack>
        </Alert>
      )}

      <Grid>
        <Grid.Col span={{ base: 12, md: 8 }}>
          <SectionCard title="Conversación">
            <Stack gap="md">
              <div>
                <Text size="xs" c="dimmed">Lo que contó al abrirlo, el {formatFechaHora(t.created_at)}</Text>
                <Text size="sm" style={{ whiteSpace: 'pre-line' }}>{t.descripcion}</Text>
              </div>
              <ConversacionTicket ticket={t} usuarioId={usuario?.id ?? null} puedeEscribir={!final && t.estado !== 'resuelto'} />
            </Stack>
          </SectionCard>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <Stack gap="md">
            <SectionCard title="Datos">
              <DetailList columnas={1} items={[
                { label: 'Lo atiende', value: t.tecnico?.nombre ?? 'Todavía nadie' },
                { label: 'Área de TIC', value: t.unidad_tic?.nombre ?? '—' },
                { label: 'Equipo', value: t.bien ? `${t.bien.codigo_institucional} · ${[t.bien.tipo, t.bien.marca, t.bien.modelo].filter(Boolean).join(' ')}` : '—' },
                ...(t.encuesta ? [{ label: 'Su calificación', value: `${t.encuesta.calificacion} de 5` }] : []),
                ...(t.motivo_anulacion ? [{ label: 'Motivo de la anulación', value: t.motivo_anulacion }] : []),
              ]} />
            </SectionCard>
            <SectionCard title="Seguimiento">
              <Timeline bulletSize={12} lineWidth={2} active={(t.historial ?? []).length - 1}>
                {(t.historial ?? []).map((h, n) => (
                  <Timeline.Item key={n} title={ESTADO_PARA_EL_SOLICITANTE[h.estado_nuevo]}>
                    <Text size="xs" c="dimmed">{formatFechaHora(h.fecha)}</Text>
                  </Timeline.Item>
                ))}
              </Timeline>
            </SectionCard>
          </Stack>
        </Grid.Col>
      </Grid>

      <ConfirmarTicketModal ticket={confirmando ? t : null} onClose={() => setConfirmando(false)} />
      <MotivoModal
        opened={motivo !== null}
        onClose={() => setMotivo(null)}
        title={motivo === 'reabrir' ? 'Sigue sin funcionar' : 'Anular el ticket'}
        descripcion={motivo === 'reabrir'
          ? 'Cuéntele a TIC qué sigue fallando: el ticket vuelve a quien lo atendió.'
          : 'Si ya se arregló o lo abrió por error, TIC no lo atenderá.'}
        confirmLabel={motivo === 'reabrir' ? 'Reabrir' : 'Anular'}
        etiqueta={motivo === 'reabrir' ? 'Qué sigue fallando' : 'Por qué ya no lo necesita'}
        placeholder={motivo === 'reabrir' ? 'Ej.: Volvió a salir el mismo error esta mañana.' : 'Ej.: Ya se arregló solo, o lo abrí por error.'}
        destructiva={motivo === 'anular'}
        cargando={mutacion.isPending}
        onConfirm={(m) => mutacion.mutate({ id: t.id, motivo: m }, { onSuccess: () => setMotivo(null) })}
      />
    </PageShell>
  )
}
