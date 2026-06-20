'use client'

import { Stack, Card, Text, Group, Button, Alert } from '@mantine/core'
import { IconCheck, IconClipboardCheck } from '@tabler/icons-react'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  agenda:           AgendaMedica
  onTomarTriaje:    () => void
  onMasTarde:       () => void
}

export function OfrecerTriajeInmediato({
  agenda, onTomarTriaje, onMasTarde,
}: Props) {
  return (
    <Card withBorder radius="lg" p="lg">
      <Stack gap="md" align="center">
        <Alert
          icon={<IconCheck size={16} />}
          color="emerald"
          variant="light"
          w="100%"
        >
          <Text size="sm" fw={600}>
            Turno {agenda.folio} creado
          </Text>
        </Alert>

        {agenda.requiere_triaje ? (
          <>
            <Text size="sm" ta="center">
              Este turno requiere triaje.
              ¿Deseas tomar los signos vitales
              ahora mismo?
            </Text>
            <Group>
              <Button variant="default" onClick={onMasTarde}>
                Más tarde
              </Button>
              <Button
                color="emerald"
                leftSection={<IconClipboardCheck size={14} />}
                onClick={onTomarTriaje}
              >
                Tomar triaje ahora
              </Button>
            </Group>
          </>
        ) : (
          <Text size="sm" c="dimmed" ta="center">
            Este turno no requiere triaje.
            El paciente puede esperar a ser
            llamado por el profesional.
          </Text>
        )}
      </Stack>
    </Card>
  )
}
