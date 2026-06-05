'use client'

import { Stack, Text, Card, Group, Badge } from '@mantine/core'
import { IconMapPin } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { viaticoService } from '../services/viaticoService'
import type { DestinoViatico } from '@/types/api'

interface Props { viaticoId: number }

export function DestinosList({ viaticoId }: Props) {
  const { data: destinos = [], isLoading } = useQuery({
    queryKey: ['destinos', viaticoId],
    queryFn:  () => viaticoService.destinos.listar(viaticoId),
    enabled:  !!viaticoId,
  })

  if (isLoading) {
    return <Text size="sm" c="dimmed">Cargando destinos...</Text>
  }

  if ((destinos as DestinoViatico[]).length === 0) {
    return (
      <Text size="sm" c="dimmed" ta="center" py="md">
        Sin destinos registrados.
      </Text>
    )
  }

  return (
    <Stack gap="xs">
      {(destinos as DestinoViatico[]).map((d, i) => (
        <Card key={d.id} withBorder radius="md" p="sm">
          <Group gap="xs" mb={4}>
            <IconMapPin size={14} />
            <Text size="sm" fw={500}>
              Destino {i + 1}
            </Text>
            <Badge size="xs" variant="light" color="blue">
              {(d.tipo_destino as string) === 'nacional'
                ? 'Nacional' : 'Internacional'}
            </Badge>
          </Group>
          <Group gap="xl">
            <div>
              <Text size="xs" c="dimmed">Llegada</Text>
              <Text size="sm">
                {d.fecha_llegada
                  ? new Date(d.fecha_llegada as string)
                      .toLocaleDateString('es-EC', {
                        timeZone: 'UTC',
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                      })
                  : '—'}
              </Text>
            </div>
            <div>
              <Text size="xs" c="dimmed">Salida</Text>
              <Text size="sm">
                {d.fecha_salida
                  ? new Date(d.fecha_salida as string)
                      .toLocaleDateString('es-EC', {
                        timeZone: 'UTC',
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                      })
                  : '—'}
              </Text>
            </div>
          </Group>
        </Card>
      ))}
    </Stack>
  )
}
