'use client'

import {
  Card, Group, Stack, Text,
  Avatar, Badge, Button, Alert,
} from '@mantine/core'
import {
  IconUser, IconUsers,
  IconAlertCircle, IconPlus,
} from '@tabler/icons-react'
import type { PacienteEncontrado } from '../services/pacienteService'

interface Props {
  paciente:        PacienteEncontrado
  onCrearHistoria: () => void
  onContinuar:     () => void
  creandoHistoria: boolean
}

export function PacienteCard({
  paciente, onCrearHistoria,
  onContinuar, creandoHistoria,
}: Props) {
  const esServidor = paciente.tipo === 'servidor'

  const initials = paciente.nombre_completo
    .split(' ')
    .slice(0, 2)
    .map(w => w[0] ?? '')
    .join('')
    .toUpperCase() || 'PA'

  return (
    <Card withBorder radius="lg" p="lg">
      <Stack gap="md">
        <Group justify="space-between" wrap="nowrap">
          <Group gap="md" wrap="nowrap">
            <Avatar
              color={esServidor ? 'emerald' : 'blue'}
              size={56}
              radius="xl"
              style={{ fontWeight: 700 }}
            >
              {initials}
            </Avatar>
            <Stack gap={2}>
              <Text fw={700} size="md">
                {paciente.nombre_completo}
              </Text>
              <Text size="xs" c="dimmed">
                CI: {paciente.cedula}
              </Text>
              <Group gap={6} mt={4}>
                <Badge
                  size="sm"
                  variant="light"
                  color={esServidor ? 'emerald' : 'blue'}
                  leftSection={
                    esServidor
                      ? <IconUser size={12} />
                      : <IconUsers size={12} />
                  }
                >
                  {esServidor ? 'Servidor' : 'Familiar'}
                </Badge>
                {!esServidor && paciente.tipo_familiar && (
                  <Badge size="sm" variant="light" color="gray">
                    {paciente.tipo_familiar}
                  </Badge>
                )}
              </Group>
            </Stack>
          </Group>
        </Group>

        {esServidor && paciente.puesto && (
          <Text size="sm" c="dimmed">
            {paciente.puesto}
            {paciente.unidad_administrativa &&
              ` — ${paciente.unidad_administrativa}`}
          </Text>
        )}

        {!esServidor && paciente.servidor_titular && (
          <Text size="sm" c="dimmed">
            Familiar de: {paciente.servidor_titular}
          </Text>
        )}

        {!paciente.tiene_historia_clinica ? (
          <Alert
            icon={<IconAlertCircle size={14} />}
            color="orange"
            variant="light"
          >
            <Text size="xs" mb="xs">
              Este paciente no tiene historia clínica
              registrada todavía.
            </Text>
            <Button
              size="xs"
              color="orange"
              variant="light"
              leftSection={<IconPlus size={14} />}
              loading={creandoHistoria}
              onClick={onCrearHistoria}
            >
              Crear historia clínica
            </Button>
          </Alert>
        ) : (
          <Button
            color="emerald"
            onClick={onContinuar}
          >
            Continuar — Crear turno
          </Button>
        )}
      </Stack>
    </Card>
  )
}
