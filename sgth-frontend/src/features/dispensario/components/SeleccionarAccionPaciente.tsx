'use client'

import {
  Stack, Card, Group, Text, Avatar,
  SimpleGrid, ThemeIcon, Button,
} from '@mantine/core'
import {
  IconUser, IconUsers, IconStethoscope,
  IconVaccine, IconArrowLeft,
} from '@tabler/icons-react'
import type { PacienteEncontrado } from '../services/pacienteService'
import { StatusBadge } from '@/components/ui'

export type AccionPaciente = 'turno' | 'servicio_enfermeria'

interface Props {
  paciente:   PacienteEncontrado
  onElegir:   (accion: AccionPaciente) => void
  onVolver:   () => void
}

export function SeleccionarAccionPaciente({
  paciente, onElegir, onVolver,
}: Props) {
  const esServidor = paciente.tipo === 'servidor'

  return (
    <Stack gap="md">
      <Card
        withBorder radius="md" p="sm"
        style={{ backgroundColor: 'var(--sgth-accent-light)' }}
      >
        <Group justify="space-between">
          <Group gap="sm">
            <Avatar
              radius="xl"
            >
              {esServidor
                ? <IconUser size={16} />
                : <IconUsers size={16} />}
            </Avatar>
            <Stack gap={0}>
              <Text size="sm" fw={600}>
                {paciente.nombre_completo}
              </Text>
              <StatusBadge size="xs">
                {esServidor ? 'Servidor' : 'Familiar'}
              </StatusBadge>
            </Stack>
          </Group>
          <Button
            size="xs"
            variant="subtle"
            leftSection={<IconArrowLeft size={12} />}
            onClick={onVolver}
          >
            Cambiar
          </Button>
        </Group>
      </Card>

      <Text size="sm" c="dimmed">
        ¿Qué necesita este paciente?
      </Text>

      <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="md">
        <Card
          withBorder
          radius="lg"
          p="lg"
          style={{ cursor: 'pointer' }}
          onClick={() => onElegir('turno')}
        >
          <Stack gap="sm" align="center">
            <ThemeIcon
              variant="light"
              size={48}
              radius="xl"
            >
              <IconStethoscope size={24} />
            </ThemeIcon>
            <Text fw={600} size="sm" ta="center">
              Turno con médico u odontólogo
            </Text>
            <Text size="xs" c="dimmed" ta="center">
              Crea un turno en la cola de espera
            </Text>
          </Stack>
        </Card>

        <Card
          withBorder
          radius="lg"
          p="lg"
          style={{ cursor: 'pointer' }}
          onClick={() => onElegir('servicio_enfermeria')}
        >
          <Stack gap="sm" align="center">
            <ThemeIcon
              variant="light"
              size={48}
              radius="xl"
            >
              <IconVaccine size={24} />
            </ThemeIcon>
            <Text fw={600} size="sm" ta="center">
              Servicio de enfermería
            </Text>
            <Text size="xs" c="dimmed" ta="center">
              Inyección, curación u otro
              procedimiento directo
            </Text>
          </Stack>
        </Card>
      </SimpleGrid>
    </Stack>
  )
}
