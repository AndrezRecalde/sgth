'use client'

import { Button, Group, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconMapPin, IconMapPinOff, IconRefresh } from '@tabler/icons-react'
import { ModalFooter, SgthModal, StatusBadge } from '@/components/ui'
import type { Coordenadas, EstadoUbicacion } from '../hooks/useUbicacion'

interface Props {
  estado: EstadoUbicacion
  coordenadas: Coordenadas | null
  onReintentar: () => void
}

/** Si el GPS está disponible, con qué precisión, y qué hacer si no lo está. */
export function UbicacionEstado({ estado, coordenadas, onReintentar }: Props) {
  const [instrucciones, { open: abrir, close: cerrar }] = useDisclosure(false)

  if (estado === 'buscando') {
    return <StatusBadge variant="dot">Obteniendo ubicación…</StatusBadge>
  }

  if (estado === 'lista' && coordenadas) {
    return (
      <Stack gap={2} align="flex-end">
        <StatusBadge tone="success" leftSection={<IconMapPin size={12} />}>
          GPS activo
        </StatusBadge>
        <Text size="xs" c="dimmed">
          Precisión de {coordenadas.precision} m
        </Text>
      </Stack>
    )
  }

  return (
    <Group gap="xs" justify="flex-end">
      <StatusBadge tone="danger" leftSection={<IconMapPinOff size={12} />}>
        GPS inactivo
      </StatusBadge>
      {estado === 'denegada' ? (
        <Button size="compact-xs" variant="subtle" onClick={abrir}>
          Cómo permitirlo
        </Button>
      ) : estado === 'error' ? (
        <Button size="compact-xs" variant="subtle" leftSection={<IconRefresh size={12} />} onClick={onReintentar}>
          Reintentar
        </Button>
      ) : null}

      <SgthModal opened={instrucciones} onClose={cerrar} title="Ubicación bloqueada">
        <Stack gap="md">
          <Text size="sm">
            El navegador tiene bloqueado el acceso a la ubicación, y la
            marcación en línea la necesita. Para permitirlo, pulse el icono de
            candado o de información junto a la dirección de la página, busque
            «Ubicación» y cámbiela a «Permitir». Luego recargue la página.
          </Text>
          <ModalFooter onCancel={cerrar} cancelLabel="Entendido" sinPrincipal />
        </Stack>
      </SgthModal>
    </Group>
  )
}
