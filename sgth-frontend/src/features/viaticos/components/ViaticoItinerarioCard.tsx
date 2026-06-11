'use client'

import {
  Card, Group, Text, Button,
  Divider, Alert, ThemeIcon,
} from '@mantine/core'
import { IconRoute, IconAlertCircle } from '@tabler/icons-react'
import { TramosList } from './TramosList'
import type { ViaticoConRelaciones } from '@/types/api'

interface Props {
  viatico:     ViaticoConRelaciones
  puedeEditar: boolean
  onGestionar: () => void
}

export function ViaticoItinerarioCard({
  viatico: d,
  puedeEditar,
  onGestionar,
}: Props) {
  return (
    <Card withBorder radius="md" h="100%">
      <Group justify="space-between" mb="sm">
        <Group gap="xs">
          <ThemeIcon variant="default" size="sm">
            <IconRoute size={14} />
          </ThemeIcon>
          <Text fw={600} size="sm">Itinerario del viaje</Text>
        </Group>
        {puedeEditar && (
          <Button
            size="xs"
            variant="light"
            onClick={onGestionar}
          >
            Gestionar
          </Button>
        )}
      </Group>
      <Divider mb="sm" />
      <TramosList viaticoId={d.id} puedeEditar={false} />
      {!d.datetime_salida && (
        <Alert
          icon={<IconAlertCircle size={14} />}
          color="orange"
          variant="light"
          mt="sm"
        >
          <Text size="xs">
            Aun no hay tramos registrados. Pulse{' '}
            <strong>Gestionar</strong> para agregar el itinerario.
          </Text>
        </Alert>
      )}
    </Card>
  )
}
