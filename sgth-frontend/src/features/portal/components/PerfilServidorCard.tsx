'use client'

import {
  Card, Group, Stack, Text,
  Avatar, Badge, Divider,
} from '@mantine/core'
import type { UsuarioAuth } from '@/store/auth.store'

interface Props {
  usuario: UsuarioAuth
}

export function PerfilServidorCard({ usuario }: Props) {
  const servidor = usuario.servidor

  const nombreCompleto = usuario.nombre_completo
    || [servidor?.nombre, servidor?.apellido]
         .filter(Boolean).join(' ')
    || usuario.email

  const initials = nombreCompleto
    .split(' ')
    .slice(0, 2)
    .map((w: string) => w[0] ?? '')
    .join('')
    .toUpperCase() || 'US'

  const activo = servidor?.activo ?? usuario.activo ?? false

  return (
    <Card withBorder radius="xl" p="xl">
      <Stack gap="lg">
        <Stack gap={2} align="center">
          <Text
            size="xs"
            fw={600}
            c="dimmed"
            tt="uppercase"
            ta="center"
            style={{ letterSpacing: '0.04em' }}
          >
            Gobierno Autónomo Descentralizado
          </Text>
          <Text
            size="xs"
            fw={600}
            c="dimmed"
            tt="uppercase"
            ta="center"
            style={{ letterSpacing: '0.04em' }}
          >
            de la Provincia de Esmeraldas
          </Text>
        </Stack>

        <Divider />

        <Group gap="lg" wrap="nowrap">
          <Avatar
            color="emerald"
            size={84}
            radius="xl"
            style={{ fontSize: 28, fontWeight: 700 }}
          >
            {initials}
          </Avatar>

          <Stack gap={4} style={{ flex: 1 }}>
            <Text fw={700} size="lg">
              {nombreCompleto}
            </Text>

            {servidor?.puesto?.nombre && (
              <Text size="sm" c="dimmed">
                {servidor.puesto.nombre}
              </Text>
            )}

            {servidor?.unidad_administrativa?.nombre && (
              <Text size="sm" c="dimmed">
                {servidor.unidad_administrativa.nombre}
              </Text>
            )}

            <Group gap={6} mt={6}>
              {servidor?.tipo_nombramiento_label && (
                <Badge size="sm" variant="light" color="blue">
                  {servidor.tipo_nombramiento_label}
                </Badge>
              )}
              <Badge
                size="sm"
                variant="light"
                color={activo ? 'emerald' : 'red'}
              >
                {activo ? 'Activo' : 'Inactivo'}
              </Badge>
            </Group>
          </Stack>
        </Group>
      </Stack>
    </Card>
  )
}
