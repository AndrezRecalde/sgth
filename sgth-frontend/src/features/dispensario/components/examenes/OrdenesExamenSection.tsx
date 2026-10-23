'use client'

import { useState } from 'react'
import { Button, Card, Group, Stack, Text } from '@mantine/core'
import { IconBan, IconFlask, IconPlus } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { DataState, StatusBadge, TableActions } from '@/components/ui'
import type { SemanticTone } from '@/config/design.tokens'
import { useAuthStore } from '@/store/auth.store'
import { formatFechaMes } from '@/lib/fecha'
import { useAnularOrdenExamen, useOrdenesExamen } from '../../hooks/useExamenes'
import { AnularRegistroModal, MOTIVOS_ANULAR_ORDEN_EXAMEN } from '../AnularRegistroModal'
import { SolicitarExamenesModal } from './SolicitarExamenesModal'
import type { OrdenExamen } from '../../services/examenService'
import type { ConsultaMedica } from '../../services/consultaMedicaService'

interface Props {
  consulta:          ConsultaMedica
  historiaClinicaId: number
}

const ESTADO: Record<OrdenExamen['estado'], { label: string; tone: SemanticTone }> = {
  emitida:  { label: 'Emitida',  tone: 'info'    },
  parcial:  { label: 'Parcial',  tone: 'warning' },
  completa: { label: 'Completa', tone: 'success' },
  anulada:  { label: 'Anulada',  tone: 'danger'  },
}

const abierta = (o: OrdenExamen) => o.estado === 'emitida' || o.estado === 'parcial'

/** Las órdenes de exámenes de esta consulta, y el botón para pedir otra. */
export function OrdenesExamenSection({ consulta, historiaClinicaId }: Props) {
  const [solicitarOpened, { open: abrirSolicitar, close: cerrarSolicitar }] = useDisclosure(false)
  const [anulando, setAnulando] = useState<OrdenExamen | null>(null)
  const { data: ordenes = [], isLoading, error, refetch } = useOrdenesExamen(historiaClinicaId, consulta.id)
  const anular = useAnularOrdenExamen()
  const { usuario, hasRole } = useAuthStore()

  // Como el servidor: pide quien atendió la consulta (la orden sale con su
  // firma); anula quien la emitió o la administración, mientras esté abierta.
  const puedeSolicitar = consulta.medico_id === usuario?.id
  const puedeAnular = (o: OrdenExamen) =>
    abierta(o) && (o.solicitado_por === usuario?.id || hasRole('admin-dispensario'))

  return (
    <Stack gap="sm">
      <Group justify="space-between">
        <Text size="sm" fw={500}>
          Órdenes de exámenes
          {ordenes.length > 0 && <Text span c="dimmed" ml={4}>({ordenes.length})</Text>}
        </Text>
        {puedeSolicitar && (
          <Button size="xs" leftSection={<IconPlus size={13} />} onClick={abrirSolicitar}>
            Solicitar exámenes
          </Button>
        )}
      </Group>

      <DataState loading={isLoading} error={error} onRetry={() => refetch()}
        errorTitle="No se pudieron cargar las órdenes"
        empty={!ordenes.length}
        emptyProps={{ icon: IconFlask, title: 'Sin órdenes', description: 'No se han pedido exámenes en esta consulta.' }}
        skeletonRows={2}>
        <Stack gap="sm">
          {ordenes.map((o) => (
            <Card key={o.id} withBorder radius="md" p="sm">
              <Group justify="space-between" wrap="nowrap" align="flex-start">
                <Stack gap={4}>
                  <Group gap="xs">
                    <Text size="sm" fw={600} ff="monospace">{o.folio}</Text>
                    <StatusBadge size="xs" tone={ESTADO[o.estado].tone}>{ESTADO[o.estado].label}</StatusBadge>
                    {o.vencida && <StatusBadge size="xs" tone="danger">Vencida</StatusBadge>}
                    {o.prioridad === 'urgente' && <StatusBadge size="xs" tone="warning">Urgente</StatusBadge>}
                    <Text size="xs" c="dimmed">{formatFechaMes(o.fecha_solicitud)}</Text>
                  </Group>
                  <Text size="xs">
                    {o.items.map((i) => {
                      const nombre = i.examen?.nombre ?? 'Examen retirado del catálogo'
                      return i.nota ? `${nombre} (${i.nota})` : nombre
                    }).join(' · ')}
                  </Text>
                  {o.estado === 'anulada' && o.motivo_anulacion && (
                    <Text size="xs" c="dimmed">Anulada: {o.motivo_anulacion}</Text>
                  )}
                </Stack>
                {puedeAnular(o) && (
                  <TableActions actions={[{
                    label: 'Anular orden', icon: <IconBan size={14} />, color: 'red',
                    onClick: () => setAnulando(o),
                  }]} />
                )}
              </Group>
            </Card>
          ))}
        </Stack>
      </DataState>

      <SolicitarExamenesModal opened={solicitarOpened} onClose={cerrarSolicitar} consulta={consulta} />

      <AnularRegistroModal
        opened={anulando !== null}
        onClose={() => setAnulando(null)}
        titulo="Anular orden de exámenes"
        descripcion={`Orden ${anulando?.folio ?? ''}: dejará de esperar resultados.`}
        motivos={MOTIVOS_ANULAR_ORDEN_EXAMEN}
        loading={anular.isPending}
        onConfirmar={(motivo) => {
          if (!anulando) return
          anular.mutate({ id: anulando.id, motivo }, { onSuccess: () => setAnulando(null) })
        }}
      />
    </Stack>
  )
}
