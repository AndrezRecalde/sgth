'use client'

import {
  Stack, Card, Group, Text, Avatar,
  Badge, SimpleGrid, ThemeIcon, Button,
} from '@mantine/core'
import {
  IconUser, IconUsers, IconStethoscope,
  IconVaccine, IconArrowLeft,
} from '@tabler/icons-react'
import type { PacienteEncontrado } from '../services/pacienteService'

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
        style={{ backgroundColor: 'var(--mantine-color-blue-light)' }}
      >
        <Group justify="space-between">
          <Group gap="sm">
            <Avatar
              color={esServidor ? 'emerald' : 'blue'}
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
              <Badge size="xs" variant="light">
                {esServidor ? 'Servidor' : 'Familiar'}
              </Badge>
            </Stack>
          </Group>
          <Button
            size="xs"
            variant="subtle"
            color="gray"
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
              color="emerald"
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
              color="violet"
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
