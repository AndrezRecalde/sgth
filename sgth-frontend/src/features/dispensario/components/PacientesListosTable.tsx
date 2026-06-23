'use client'

import {
  Stack, Group, Text, Badge, Avatar,
  Button, Skeleton,
} from '@mantine/core'
import { IconUser, IconUsers, IconStethoscope } from '@tabler/icons-react'
import { EmptyState } from '@/components/ui/EmptyState'
import { useListosParaConsulta } from '../hooks/useAgenda'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  onAtender: (turno: AgendaMedica) => void
}

export function PacientesListosTable({ onAtender }: Props) {
  const { data: turnos = [], isLoading } = useListosParaConsulta()

  if (isLoading) {
    return (
      <Stack gap="xs">
        <Skeleton height={56} radius="md" />
        <Skeleton height={56} radius="md" />
      </Stack>
    )
  }

  if (turnos.length === 0) {
    return (
      <EmptyState
        icon={IconStethoscope}
        title="Sin pacientes en espera"
        description="No tienes pacientes listos para
          consulta en este momento."
      />
    )
  }

  return (
    <Stack gap="xs">
      {turnos.map((turno, i) => {
        const esServidor = !!turno.servidor_id
        const nombrePaciente = esServidor
          ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
          : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

        const tieneTriaje = !!turno.triaje

        return (
          <Group
            key={turno.id}
            justify="space-between"
            p="sm"
            wrap="nowrap"
            style={{
              border: '1px solid var(--mantine-color-gray-2)',
              borderRadius: 8,
              backgroundColor: i === 0
                ? 'var(--mantine-color-emerald-light)'
                : undefined,
            }}
          >
            <Group gap="sm" wrap="nowrap">
              <Badge size="lg" circle color="gray" variant="light">
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
                <Text size="xs" c="dimmed" ff="monospace">
                  {turno.folio}
                </Text>
              </Stack>
            </Group>

            <Group gap="xs" wrap="nowrap">
              <Badge
                size="sm"
                variant="light"
                color={turno.tipo_atencion === 'odontologia' ? 'cyan' : 'blue'}
              >
                {turno.tipo_atencion === 'odontologia'
                  ? 'Odontología'
                  : tieneTriaje ? 'Triaje listo' : 'Medicina General'}
              </Badge>
              <Button
                size="xs"
                color="emerald"
                onClick={() => onAtender(turno)}
              >
                Atender
              </Button>
            </Group>
          </Group>
        )
      })}
    </Stack>
  )
}
