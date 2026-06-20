'use client'

import {
  Stack, Group, Text, Badge, Avatar,
  ActionIcon, Tooltip, Skeleton,
} from '@mantine/core'
import {
  IconUser, IconUsers, IconX, IconStethoscope,
} from '@tabler/icons-react'
import { EmptyState } from '@/components/ui/EmptyState'
import type { AgendaMedica } from '../services/agendaService'

const ESTADO_COLORS: Record<string, string> = {
  en_espera:  'orange',
  en_sala:    'blue',
  atendida:   'emerald',
  cancelada:  'red',
}

const ESTADO_LABELS: Record<string, string> = {
  en_espera:  'En espera',
  en_sala:    'En sala / Triaje',
  atendida:   'Atendido',
  cancelada:  'Cancelado',
}

interface Props {
  turnos:      AgendaMedica[]
  isLoading:   boolean
  onCancelar?: (id: number) => void
  onAtender?:  (turno: AgendaMedica) => void
}

export function ColaTurnosTable({
  turnos, isLoading, onCancelar, onAtender,
}: Props) {
  if (isLoading) {
    return (
      <Stack gap="xs">
        <Skeleton height={56} radius="md" />
        <Skeleton height={56} radius="md" />
        <Skeleton height={56} radius="md" />
      </Stack>
    )
  }

  if (turnos.length === 0) {
    return (
      <EmptyState
        icon={IconStethoscope}
        title="Sin turnos en la cola"
        description="No hay pacientes en espera para
          esta fecha."
      />
    )
  }

  return (
    <Stack gap="xs">
      {turnos.map((turno, i) => {
        const esServidor = !!turno.servidor_id
        const paciente = esServidor
          ? turno.servidor
          : turno.carga_familiar

        const nombrePaciente = esServidor
          ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
          : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

        const hora = turno.registrado_en
          ? new Date(turno.registrado_en).toLocaleTimeString(
              'es-EC', { hour: '2-digit', minute: '2-digit' }
            )
          : '—'

        return (
          <Group
            key={turno.id}
            justify="space-between"
            p="sm"
            wrap="nowrap"
            style={{
              border: '1px solid var(--mantine-color-gray-2)',
              borderRadius: 8,
            }}
          >
            <Group gap="sm" wrap="nowrap">
              <Badge
                size="lg"
                circle
                color="gray"
                variant="light"
              >
                {i + 1}
              </Badge>
              <Avatar
                color={esServidor ? 'emerald' : 'blue'}
                radius="xl"
                size="sm"
              >
                {esServidor
                  ? <IconUser size={14} />
                  : <IconUsers size={14} />}
              </Avatar>
              <Stack gap={0}>
                <Text size="sm" fw={600}>
                  {nombrePaciente.trim() || '—'}
                </Text>
                <Group gap={6}>
                  <Text size="xs" c="dimmed" ff="monospace">
                    {turno.folio}
                  </Text>
                  <Text size="xs" c="dimmed">
                    · {hora}
                  </Text>
                </Group>
                <Group gap={6} mt={2}>
                  <Badge
                    size="xs"
                    variant="dot"
                    color={turno.tipo_atencion === 'odontologia'
                      ? 'cyan' : 'blue'}
                  >
                    {turno.tipo_atencion === 'odontologia'
                      ? 'Odontología' : 'Medicina General'}
                  </Badge>
                  <Text size="xs" c="dimmed">
                    Dr(a). {turno.medico?.nombre_completo
                      ?? turno.medico?.usuario_ti ?? '—'}
                  </Text>
                </Group>
              </Stack>
            </Group>

            <Group gap="xs" wrap="nowrap">
              <Badge
                size="sm"
                variant="light"
                color={ESTADO_COLORS[turno.estado] ?? 'gray'}
              >
                {ESTADO_LABELS[turno.estado] ?? turno.estado}
              </Badge>

              {turno.estado === 'en_espera' && onCancelar && (
                <Tooltip label="Cancelar turno" withArrow>
                  <ActionIcon
                    variant="subtle"
                    color="red"
                    size="sm"
                    onClick={() => onCancelar(turno.id)}
                  >
                    <IconX size={14} />
                  </ActionIcon>
                </Tooltip>
              )}
            </Group>
          </Group>
        )
      })}
    </Stack>
  )
}
