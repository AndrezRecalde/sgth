'use client'

import { useState } from 'react'
import { Button, Group, Stack, Text } from '@mantine/core'
import { IconFileInvoice, IconFileTypePdf, IconTruckDelivery } from '@tabler/icons-react'
import { MotivoModal, SectionCard, StatusBadge } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_SOLICITUD } from '../constants/consumibles'
import { useSolicitudMutations, useSolicitudes } from '../hooks/useSolicitudes'
import type { SolicitudBodega } from '../services/solicitudesService'
import { DespacharSolicitudModal } from './DespacharSolicitudModal'
import { NuevaSolicitudModal, type TicketDeLaSolicitud } from './NuevaSolicitudModal'

interface Props {
  ticket: TicketDeLaSolicitud
  /** Cerrado o anulado: ya no se pide nada. */
  final: boolean
}

/**
 * La solicitud a bodega en un ticket «Consumibles» (5.4c): generarla con el
 * servidor y el equipo del ticket, imprimirla y, cuando Bodega despacha,
 * anotar lo despachado, que es la entrega y cierra el ticket.
 */
export function SolicitudesDelTicket({ ticket, final }: Props) {
  const { hasPermiso } = useAuth()
  const gestiona = hasPermiso('gestionar-inventario-ti')
  const { data } = useSolicitudes({ ticket_id: ticket.id }, 1, hasPermiso('ver-inventario-ti') || gestiona)
  const { anular, pdf } = useSolicitudMutations()
  const [nueva, setNueva] = useState(false)
  const [despachando, setDespachando] = useState<SolicitudBodega | null>(null)
  const [anulando, setAnulando] = useState<SolicitudBodega | null>(null)

  const solicitudes = data?.data ?? []
  const hayPendiente = solicitudes.some((s) => s.estado === 'pendiente')
  const imprimir = (s: SolicitudBodega) => pdf.mutate({ id: s.id, codigo: s.codigo })

  return (
    <SectionCard
      title="Solicitud a bodega"
      description="Lo que se pide a Bodega para este servidor; lo despachado es la entrega y cierra el ticket."
      actions={gestiona && !final && !hayPendiente
        ? <Button variant="light" size="xs" leftSection={<IconFileInvoice size={14} />} onClick={() => setNueva(true)}>Generar solicitud</Button>
        : undefined}
    >
      {solicitudes.length === 0 ? (
        <Text size="sm" c="dimmed">Todavía no se generó ninguna.</Text>
      ) : (
        <Stack gap="sm">
          {solicitudes.map((s) => (
            <Stack key={s.id} gap={4}>
              <Group justify="space-between" wrap="nowrap">
                <Text size="sm" fw={500}>{s.codigo} · {formatFecha(s.fecha)}</Text>
                <StatusBadge tone={ESTADO_SOLICITUD[s.estado].tono}>{ESTADO_SOLICITUD[s.estado].etiqueta}</StatusBadge>
              </Group>
              <Text size="xs" c="dimmed">
                {s.items.map((i) => `${i.cantidad_despachada ?? i.cantidad_solicitada} × ${i.descripcion ?? ''}`).join('; ')}
              </Text>
              <Group gap="xs">
                <Button variant="subtle" size="xs" leftSection={<IconFileTypePdf size={14} />} loading={pdf.isPending} onClick={() => imprimir(s)}>
                  Imprimir
                </Button>
                {gestiona && s.estado === 'pendiente' && (
                  <>
                    <Button variant="light" size="xs" leftSection={<IconTruckDelivery size={14} />} onClick={() => setDespachando(s)}>Anotar despacho</Button>
                    <Button variant="subtle" size="xs" color="red" onClick={() => setAnulando(s)}>Anular</Button>
                  </>
                )}
              </Group>
            </Stack>
          ))}
        </Stack>
      )}

      <NuevaSolicitudModal opened={nueva} ticket={ticket} onClose={() => setNueva(false)} onGenerada={imprimir} />
      <DespacharSolicitudModal solicitud={despachando} onClose={() => setDespachando(null)} />
      <MotivoModal
        opened={!!anulando}
        onClose={() => setAnulando(null)}
        title="Anular solicitud"
        descripcion={anulando && <>La solicitud <b>{anulando.codigo}</b> queda anulada: ya no se despacha.</>}
        confirmLabel="Anular"
        destructiva
        placeholder="Ej.: Se pidió el tóner equivocado"
        cargando={anular.isPending}
        onConfirm={(motivo) => anulando && anular.mutate({ id: anulando.id, motivo }, { onSuccess: () => setAnulando(null) })}
      />
    </SectionCard>
  )
}
