'use client'

import {
  Card, Group, Text, Stack, 
  ThemeIcon, SimpleGrid, Skeleton,
} from '@mantine/core'
import { IconHistory, IconInfoCircle } from '@tabler/icons-react'
import { useUltimoTriaje } from '../hooks/useTriaje'
import { formatFechaMes } from '@/lib/fecha'

interface Props {
  agendaId: number
}

export function UltimoTriajeReferencia({ agendaId }: Props) {
  const { data: triaje, isLoading } = useUltimoTriaje(agendaId)

  if (isLoading) {
    return <Skeleton height={120} radius="md" />
  }

  if (!triaje) {
    return (
      <Card withBorder radius="md" p="sm">
        <Group gap="xs">
          <ThemeIcon variant="light" size="sm">
            <IconInfoCircle size={13} />
          </ThemeIcon>
          <Text size="xs" c="dimmed">
            Este paciente no tiene triajes anteriores
            registrados.
          </Text>
        </Group>
      </Card>
    )
  }

  return (
    <Card
      withBorder
      radius="md"
      p="sm"
      style={{
        backgroundColor: 'var(--sgth-accent-light)',
      }}
    >
      <Group gap="xs" mb="xs">
        <ThemeIcon variant="light" size="sm">
          <IconHistory size={13} />
        </ThemeIcon>
        <Text size="xs" fw={600} c="ocean">
          Último triaje registrado — {formatFechaMes(triaje.registrado_en)}
        </Text>
      </Group>

      <SimpleGrid cols={4} spacing="xs">
        <Stack gap={0}>
          <Text size="xs" c="dimmed">Peso</Text>
          <Text size="sm" fw={500}>{triaje.peso_kg} kg</Text>
        </Stack>
        <Stack gap={0}>
          <Text size="xs" c="dimmed">Talla</Text>
          <Text size="sm" fw={500}>{triaje.talla_cm} cm</Text>
        </Stack>
        <Stack gap={0}>
          <Text size="xs" c="dimmed">IMC</Text>
          <Text size="sm" fw={500}>{triaje.imc ?? '—'}</Text>
        </Stack>
        <Stack gap={0}>
          <Text size="xs" c="dimmed">P. arterial</Text>
          <Text size="sm" fw={500}>
            {triaje.presion_sistolica}/{triaje.presion_diastolica}
          </Text>
        </Stack>
        <Stack gap={0}>
          <Text size="xs" c="dimmed">F. cardíaca</Text>
          <Text size="sm" fw={500}>{triaje.frecuencia_cardiaca} lpm</Text>
        </Stack>
        <Stack gap={0}>
          <Text size="xs" c="dimmed">F. respiratoria</Text>
          <Text size="sm" fw={500}>{triaje.frecuencia_respiratoria} rpm</Text>
        </Stack>
        <Stack gap={0}>
          <Text size="xs" c="dimmed">Temperatura</Text>
          <Text size="sm" fw={500}>{triaje.temperatura_c} °C</Text>
        </Stack>
        <Stack gap={0}>
          <Text size="xs" c="dimmed">Sat. O2</Text>
          <Text size="sm" fw={500}>{triaje.saturacion_oxigeno}%</Text>
        </Stack>
      </SimpleGrid>
    </Card>
  )
}
