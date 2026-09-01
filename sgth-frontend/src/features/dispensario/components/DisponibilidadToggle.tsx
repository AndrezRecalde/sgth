'use client'

import { Switch, Group, Text, Badge, Skeleton } from '@mantine/core'
import {
  useMiDisponibilidad,
  useAlternarDisponibilidad,
} from '../hooks/useDisponibilidad'
import { useAuth } from '@/hooks/useAuth'

const ROLES_CLINICOS = ['medico', 'odontologo']

export function DisponibilidadToggle() {
  const { usuario } = useAuth()
  const { data, isLoading } = useMiDisponibilidad()
  const alternar = useAlternarDisponibilidad()

  // Marcarse disponible solo tiene sentido para quien atiende pacientes; al
  // resto del personal no se le muestra nada. La comprobación vivía en la
  // página, que por lo demás no necesitaba ser de cliente.
  const roles = (usuario?.roles as string[]) ?? []
  if (!roles.some(r => ROLES_CLINICOS.includes(r))) {
    return null
  }

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
