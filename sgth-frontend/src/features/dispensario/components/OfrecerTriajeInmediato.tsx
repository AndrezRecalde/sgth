'use client'

import { Stack, Card, Text, Group, Button, Alert } from '@mantine/core'
import { IconCheck, IconClipboardCheck } from '@tabler/icons-react'
import { usePuedeTriar } from '../hooks/usePuedeTriar'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  agenda:        AgendaMedica
  onTomarTriaje: () => void
  /** El paciente queda en la cola: sin triaje, o con el triaje para después. */
  onTerminar:    () => void
}

export function OfrecerTriajeInmediato({
  agenda, onTomarTriaje, onTerminar,
}: Props) {
  const puedeTriar = usePuedeTriar()
  const ofrecerTriaje = agenda.requiere_triaje && puedeTriar

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

        <Text size="sm" ta="center" maw={420}>
          {ofrecerTriaje
            ? '¿Toma los signos vitales ahora? Si no, el turno queda en «Pendientes de triaje».'
            : agenda.requiere_triaje
              ? 'Enfermería le tomará el triaje desde la cola.'
              : 'Este turno no requiere triaje: el paciente espera a que lo llame el profesional.'}
        </Text>

        {/* Sin triaje solo había un texto, sin un solo botón: la persona
            tenía que salir por el menú para atender al siguiente. */}
        <Group>
          <Button variant={ofrecerTriaje ? 'default' : 'filled'} onClick={onTerminar}>
            {ofrecerTriaje ? 'Más tarde' : 'Terminar'}
          </Button>
          {ofrecerTriaje && (
            <Button
              leftSection={<IconClipboardCheck size={14} />}
              onClick={onTomarTriaje}
            >
              Tomar triaje ahora
            </Button>
          )}
        </Group>
      </Stack>
    </Card>
  )
}
