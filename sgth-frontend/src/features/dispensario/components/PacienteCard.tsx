'use client'

import {
  Card, Group, Stack, Text,
  Avatar, Button, Alert, SimpleGrid,
} from '@mantine/core'
import {
  IconUser, IconUsers, IconAlertCircle, IconPlus,
  IconStethoscope, IconVaccine, IconClipboardCheck,
} from '@tabler/icons-react'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AgendaMedica } from '../services/agendaService'
import { ESTADO_TURNO_LABELS, EN_ESPERA } from '../constants/turnos'
import { StatusBadge } from '@/components/ui'

export type AccionPaciente = 'turno' | 'servicio_enfermeria'

interface Props {
  paciente:        PacienteEncontrado
  creandoHistoria: boolean
  onCrearHistoria: () => void
  onElegir:        (accion: AccionPaciente) => void
  /** El turno que el paciente ya tiene abierto hoy, si tiene uno. */
  turnoAbierto?:   AgendaMedica
  /** Sin él no se ofrece tomar el triaje de ese turno (quien no puede registrarlo). */
  onTomarTriaje?:  (turno: AgendaMedica) => void
}

/**
 * El paciente encontrado, con lo que se puede hacer por él.
 *
 * Las dos acciones van aquí mismo: antes había un «Continuar» que llevaba a
 * una pantalla solo para elegir entre turno y servicio, un clic y una pantalla
 * más por cada paciente de la fila.
 */
export function PacienteCard({
  paciente, creandoHistoria, onCrearHistoria, onElegir, turnoAbierto, onTomarTriaje,
}: Props) {
  const esServidor = paciente.tipo === 'servidor'

  const iniciales = paciente.nombre_completo
    .split(' ')
    .slice(0, 2)
    .map(w => w[0] ?? '')
    .join('')
    .toUpperCase() || 'PA'

  const pendienteDeTriaje = turnoAbierto?.estado === EN_ESPERA
    && turnoAbierto.requiere_triaje && !turnoAbierto.triaje

  return (
    <Card withBorder radius="lg" p="md">
      <Stack gap="md">
        <Group gap="md" wrap="nowrap">
          <Avatar size={48} radius="xl" fw={700}>
            {iniciales}
          </Avatar>
          <Stack gap={2}>
            <Text fw={700}>{paciente.nombre_completo}</Text>
            <Group gap={6}>
              <Text size="xs" c="dimmed">CI: {paciente.cedula}</Text>
              <StatusBadge size="xs" leftSection={esServidor ? <IconUser size={12} /> : <IconUsers size={12} />}>
                {esServidor ? 'Servidor' : 'Familiar'}
              </StatusBadge>
              {!esServidor && paciente.tipo_familiar && (
                <StatusBadge size="xs">{paciente.tipo_familiar}</StatusBadge>
              )}
            </Group>
            <Text size="xs" c="dimmed">
              {esServidor
                ? [paciente.puesto, paciente.unidad_administrativa].filter(Boolean).join(' — ')
                : paciente.servidor_titular && `Familiar de: ${paciente.servidor_titular}`}
            </Text>
          </Stack>
        </Group>

        {/* El turno duplicado lo rechaza el backend, pero al final del
            formulario: aquí se ve antes de empezar. */}
        {turnoAbierto && (
          <Alert icon={<IconAlertCircle size={14} />} color="amber" variant="light">
            <Stack gap="xs">
              <Text size="xs">
                Ya tiene el turno <Text span ff="monospace" inherit fw={600}>{turnoAbierto.folio}</Text> de
                {turnoAbierto.tipo_atencion === 'odontologia' ? ' odontología' : ' medicina general'}:
                {' '}{(ESTADO_TURNO_LABELS[turnoAbierto.estado] ?? turnoAbierto.estado).toLowerCase()}.
              </Text>
              {pendienteDeTriaje && onTomarTriaje && (
                <Button
                  size="xs"
                  variant="light"
                  leftSection={<IconClipboardCheck size={14} />}
                  onClick={() => onTomarTriaje(turnoAbierto)}
                  w="fit-content"
                >
                  Tomar el triaje de ese turno
                </Button>
              )}
            </Stack>
          </Alert>
        )}

        {!paciente.tiene_historia_clinica ? (
          <Alert icon={<IconAlertCircle size={14} />} color="amber" variant="light">
            <Text size="xs" mb="xs">
              Este paciente no tiene historia clínica registrada todavía.
            </Text>
            <Button
              size="xs"
              variant="light"
              leftSection={<IconPlus size={14} />}
              loading={creandoHistoria}
              onClick={onCrearHistoria}
            >
              Crear historia clínica
            </Button>
          </Alert>
        ) : (
          <SimpleGrid cols={{ base: 1, xs: 2 }} spacing="sm">
            <Button leftSection={<IconStethoscope size={16} />} onClick={() => onElegir('turno')}>
              Crear turno
            </Button>
            <Button
              variant="light"
              leftSection={<IconVaccine size={16} />}
              onClick={() => onElegir('servicio_enfermeria')}
            >
              Servicio de enfermería
            </Button>
          </SimpleGrid>
        )}
      </Stack>
    </Card>
  )
}
