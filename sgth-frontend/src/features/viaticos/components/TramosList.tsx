'use client'

import {
  Stack, Text, Card, Group, Badge,
  Timeline, ActionIcon, Tooltip,
} from '@mantine/core'
import {
  IconPlane, IconBus, IconShip,
  IconMapPin, IconClock, IconAlertCircle,
  IconTrash,
} from '@tabler/icons-react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import React from 'react'
import { useTramos } from '../hooks/useViaticos'
import { viaticoService } from '../services/viaticoService'
import { getApiErrorMessage } from '@/types/api'
import type { TramoViatico } from '@/types/api'

interface Props {
  viaticoId:  number
  puedeEditar?: boolean
}

const TIPO_ICONS: Record<string, React.ReactNode> = {
  aereo:     <IconPlane size={14} />,
  terrestre: <IconBus size={14} />,
  maritimo:  <IconShip size={14} />,
}

function formatDateTime(dt?: string | null): string {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('es-EC', {
    timeZone: 'UTC',
    day:    '2-digit',
    month:  '2-digit',
    year:   'numeric',
    hour:   '2-digit',
    minute: '2-digit',
  })
}

function LugarText({
  tipo,
  provincia,
  canton,
  pais,
  ciudad,
}: {
  tipo:      string
  provincia?: { nombre?: string } | null
  canton?:   { nombre?: string } | null
  pais?:     string | null
  ciudad:    string
}) {
  if (tipo === 'nacional') {
    return (
      <Text size="sm">
        {[provincia?.nombre, canton?.nombre, ciudad]
          .filter(Boolean).join(' / ')}
      </Text>
    )
  }
  return (
    <Text size="sm">
      {[pais, ciudad].filter(Boolean).join(' / ')}
    </Text>
  )
}

export function TramosList({ viaticoId, puedeEditar }: Props) {
  const { data: tramos = [], isLoading } = useTramos(viaticoId)
  const qc = useQueryClient()

  const eliminar = useMutation({
    mutationFn: (tramoId: number) =>
      viaticoService.tramos.eliminar(viaticoId, tramoId),
    onSuccess: () => {
      notifications.show({
        title:   'Tramo eliminado',
        message: 'El tramo fue eliminado del itinerario.',
        color:   'orange',
      })
      qc.invalidateQueries({ queryKey: ['tramos', viaticoId] })
      qc.invalidateQueries({ queryKey: ['viatico', viaticoId] })
    },
    onError: (error: unknown) => notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
    }),
  })

  if (isLoading) {
    return <Text size="sm" c="dimmed">Cargando itinerario...</Text>
  }

  const lista = tramos as TramoViatico[]

  if (lista.length === 0) {
    return (
      <Text size="sm" c="dimmed" ta="center" py="md">
        Sin tramos registrados. Agrega el itinerario del viaje.
      </Text>
    )
  }

  return (
    <Stack gap="xs">
      <Timeline active={lista.length} bulletSize={24} lineWidth={2}>
        {lista.map((t, i) => {
          const tipoVehiculo =
            t.empresa?.catalogo?.tipo_vehiculo ?? 'terrestre'
          const requiereAuth =
            t.empresa?.catalogo?.requiere_autorizacion ?? false
          const estadoAuth  = t.autorizacion_vuelo?.estado

          return (
            <Timeline.Item
              key={t.id}
              bullet={TIPO_ICONS[tipoVehiculo] ?? <IconBus size={14} />}
              title={
                <Group gap="xs" justify="space-between">
                  <Group gap="xs">
                    <Text size="sm" fw={600}>
                      Tramo {t.orden}
                    </Text>
                    <Badge size="xs" variant="light" color="blue">
                      {t.empresa?.nombre ?? '—'}
                    </Badge>
                    {requiereAuth && (
                      <Badge
                        size="xs"
                        color={
                          estadoAuth === 'aprobada'  ? 'emerald' :
                          estadoAuth === 'rechazada' ? 'red'     :
                          'orange'
                        }
                        variant="dot"
                      >
                        Auth. vuelo:{' '}
                        {estadoAuth === 'aprobada'  ? 'aprobada'  :
                         estadoAuth === 'rechazada' ? 'rechazada' :
                         'pendiente'}
                      </Badge>
                    )}
                  </Group>
                  {puedeEditar && (
                    <Tooltip label="Eliminar tramo">
                      <ActionIcon
                        size="xs"
                        color="red"
                        variant="subtle"
                        loading={eliminar.isPending}
                        onClick={() => {
                          if (confirm('¿Eliminar este tramo?')) {
                            eliminar.mutate(t.id)
                          }
                        }}
                      >
                        <IconTrash size={12} />
                      </ActionIcon>
                    </Tooltip>
                  )}
                </Group>
              }
            >
              <Card
                withBorder
                radius="sm"
                p="xs"
                mt={4}
              >
                <Group gap="xl" wrap="wrap">
                  <div>
                    <Group gap={4} mb={2}>
                      <IconMapPin size={12} />
                      <Text size="xs" c="dimmed">Origen</Text>
                    </Group>
                    <LugarText
                      tipo={t.origen_tipo}
                      provincia={t.origen_provincia}
                      canton={t.origen_canton}
                      pais={t.origen_pais}
                      ciudad={t.origen_ciudad}
                    />
                    <Text size="xs" c="dimmed" mt={2}>
                      {formatDateTime(t.datetime_salida)}
                    </Text>
                  </div>
                  <div>
                    <Group gap={4} mb={2}>
                      <IconMapPin size={12} />
                      <Text size="xs" c="dimmed">Destino</Text>
                    </Group>
                    <LugarText
                      tipo={t.destino_tipo}
                      provincia={t.destino_provincia}
                      canton={t.destino_canton}
                      pais={t.destino_pais}
                      ciudad={t.destino_ciudad}
                    />
                    <Text size="xs" c="dimmed" mt={2}>
                      {formatDateTime(t.datetime_llegada)}
                    </Text>
                  </div>
                </Group>
              </Card>
            </Timeline.Item>
          )
        })}
      </Timeline>
    </Stack>
  )
}
