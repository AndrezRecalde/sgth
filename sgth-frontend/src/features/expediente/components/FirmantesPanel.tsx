'use client'

import { Alert, Badge, Card, Group, SimpleGrid, Skeleton, Text } from '@mantine/core'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { useFirmantesVigentes } from '../hooks/useFirmantes'
import type { FirmanteVigente } from '@/types/api'

function nombre(s?: { nombre?: string | null; apellido?: string | null } | null): string {
  if (!s) return '—'
  return [s.apellido, s.nombre].filter(Boolean).join(' ') || '—'
}

function Tarjeta({ firmante }: { firmante: FirmanteVigente }) {
  return (
    <Card withBorder radius="md" padding="sm">
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Text size="xs" fw={600} c="dimmed" tt="uppercase">
          {firmante.etiqueta}
        </Text>
        {firmante.subrogado && (
          <Badge color="violet" variant="light" size="sm">Subrogado</Badge>
        )}
      </Group>

      <Text size="sm" fw={500} mt={6}>
        {firmante.resuelto ? nombre(firmante.servidor) : 'Sin resolver'}
      </Text>
      <Text size="xs" c="dimmed">{firmante.cargo}</Text>

      {firmante.unidad && (
        <Text size="xs" c="dimmed" mt={4}>
          Unidad: {firmante.unidad.nombre}
        </Text>
      )}

      {!firmante.resuelto && (
        <Alert
          variant="light"
          color="orange"
          icon={<IconAlertTriangle size={14} />}
          mt="xs"
          p="xs"
        >
          <Text size="xs">{firmante.motivo_sin_resolver}</Text>
        </Alert>
      )}
    </Card>
  )
}

/**
 * Quiénes firmarán la Acción de Personal. Se muestra antes de suscribir para
 * que Talento Humano vea qué nombres quedarán impresos: al suscribir se sellan
 * en la acción y ya no cambian, aunque después rote la autoridad.
 */
export function FirmantesPanel({ compacto = false }: { compacto?: boolean }) {
  const { data: firmantes = [], isLoading } = useFirmantesVigentes()

  if (isLoading) {
    return (
      <SimpleGrid cols={{ base: 1, sm: 2 }}>
        <Skeleton height={110} radius="md" />
        <Skeleton height={110} radius="md" />
      </SimpleGrid>
    )
  }

  const sinResolver = firmantes.filter((f) => !f.resuelto)

  return (
    <>
      {!compacto && (
        <Alert variant="light" color="blue" icon={<IconInfoCircle size={16} />} mb="md">
          Los firmantes salen del organigrama: son los jefes de la unidad de
          Talento Humano y de la máxima autoridad. Para cambiarlos se cambia el
          organigrama, no hay designación aparte. Al suscribir una acción, estos
          datos se copian dentro de ella y ya no cambian.
        </Alert>
      )}

      <SimpleGrid cols={{ base: 1, sm: 2 }}>
        {firmantes.map((f) => <Tarjeta key={f.rol_firma} firmante={f} />)}
      </SimpleGrid>

      {sinResolver.length > 0 && !compacto && (
        <Alert variant="light" color="orange" icon={<IconAlertTriangle size={16} />} mt="md">
          Los documentos saldrán con el cargo pero sin nombre mientras esto no se
          resuelva. Se corrige en Estructura → Unidades, marcando la unidad
          correspondiente y asegurando que su puesto de jefatura esté ocupado.
        </Alert>
      )}
    </>
  )
}
