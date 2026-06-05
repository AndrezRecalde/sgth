'use client'

import { Stack, Text, Card, Group, Badge } from '@mantine/core'
import { IconTruck, IconPlane } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { viaticoService } from '../services/viaticoService'
import type { TransporteViatico } from '@/types/api'

interface Props { viaticoId: number }

const TIPO_ICONS: Record<string, React.ReactNode> = {
  avion:     <IconPlane size={14} />,
  terrestre: <IconTruck size={14} />,
}

export function TransportesList({ viaticoId }: Props) {
  const { data: transportes = [], isLoading } = useQuery({
    queryKey: ['transportes', viaticoId],
    queryFn:  () => viaticoService.transportes.listar(viaticoId),
    enabled:  !!viaticoId,
  })

  if (isLoading) {
    return <Text size="sm" c="dimmed">Cargando transportes...</Text>
  }

  if ((transportes as TransporteViatico[]).length === 0) {
    return (
      <Text size="sm" c="dimmed" ta="center" py="md">
        Sin transportes registrados.
      </Text>
    )
  }

  return (
    <Stack gap="xs">
      {(transportes as TransporteViatico[]).map((t) => (
        <Card key={t.id} withBorder radius="md" p="sm">
          <Group gap="xs" mb={4}>
            {TIPO_ICONS[t.tipo as string] ?? <IconTruck size={14} />}
            <Text size="sm" fw={500}>
              {(t.tipo as string).charAt(0).toUpperCase() +
               (t.tipo as string).slice(1)}
            </Text>
            {(t.tipo as string) === 'avion' && (
              <Badge size="xs" color="orange" variant="dot">
                Requiere autorización
              </Badge>
            )}
          </Group>
          <Group gap="xl">
            {(t.empresa_o_aerolinea as string | null) && (
              <div>
                <Text size="xs" c="dimmed">Empresa</Text>
                <Text size="sm">
                  {t.empresa_o_aerolinea as string}
                </Text>
              </div>
            )}
            {t.monto && (
              <div>
                <Text size="xs" c="dimmed">Monto</Text>
                <Text size="sm" ff="monospace">
                  ${Number(t.monto).toFixed(2)}
                </Text>
              </div>
            )}
            <div>
              <Text size="xs" c="dimmed">Fecha</Text>
              <Text size="sm">
                {t.fecha_viaje
                  ? new Date(t.fecha_viaje as string)
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
