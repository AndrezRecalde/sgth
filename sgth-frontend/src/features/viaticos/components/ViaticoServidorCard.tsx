'use client'

import { Card, Group, Text, Badge, ThemeIcon } from '@mantine/core'
import { IconUser } from '@tabler/icons-react'

interface Servidor {
  nombre?:          string | null
  segundo_nombre?:  string | null
  apellido?:        string | null
  segundo_apellido?: string | null
  puesto?: {
    cargo?:                  { nombre?: string } | null
    unidad_administrativa?:  { nombre?: string } | null
  } | null
}

interface Props {
  servidor:       Servidor
  nombreDisplay?: string
}

export function ViaticoServidorCard({ servidor, nombreDisplay }: Props) {
  const nombreCompleto = [
    servidor.nombre,
    servidor.segundo_nombre,
    servidor.apellido,
    servidor.segundo_apellido,
  ].filter(Boolean).join(' ')

  return (
    <Card withBorder radius="md" p="sm" bg="blue.0">
      <Group gap="sm">
        <ThemeIcon color="blue" variant="light" size="lg" radius="xl">
          <IconUser size={18} />
        </ThemeIcon>
        <div>
          <Text fw={600} size="sm">
            {nombreCompleto || nombreDisplay}
          </Text>
          <Text size="xs" c="dimmed">
            {servidor.puesto?.cargo?.nombre ?? 'Sin cargo asignado'}
          </Text>
          <Text size="xs" c="dimmed">
            {servidor.puesto?.unidad_administrativa?.nombre ?? ''}
          </Text>
        </div>
        <Badge size="xs" color="blue" variant="light" ml="auto">
          Solicitante
        </Badge>
      </Group>
    </Card>
  )
}
