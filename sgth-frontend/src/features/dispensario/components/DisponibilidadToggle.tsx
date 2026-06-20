'use client'

import { Switch, Group, Text, Badge, Skeleton } from '@mantine/core'
import {
  useMiDisponibilidad,
  useAlternarDisponibilidad,
} from '../hooks/useDisponibilidad'

export function DisponibilidadToggle() {
  const { data, isLoading } = useMiDisponibilidad()
  const alternar = useAlternarDisponibilidad()

  if (isLoading) {
    return <Skeleton height={32} width={220} radius="xl" />
  }

  const disponible = data?.disponible ?? false

  return (
    <Group gap="sm">
      <Switch
        checked={disponible}
        onChange={() => alternar.mutate()}
        disabled={alternar.isPending}
        color="emerald"
        size="md"
      />
      <Text size="sm" fw={500}>
        {disponible
          ? 'Disponible para atención'
          : 'No disponible'}
      </Text>
      <Badge
        size="xs"
        variant="light"
        color={disponible ? 'emerald' : 'gray'}
      >
        {disponible ? 'En línea' : 'Fuera de turno'}
      </Badge>
    </Group>
  )
}
