'use client'

import {
  Stack, Text, Card, Group, Badge,
  Skeleton, Collapse, Button,
  Divider, ThemeIcon,
} from '@mantine/core'
import {
  IconStethoscope, IconPill,
  IconChevronDown, IconChevronUp,
} from '@tabler/icons-react'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { consultaMedicaService } from '../services/consultaMedicaService'
import { EmptyState } from '@/components/ui/EmptyState'
import type { ConsultaMedica } from '../services/consultaMedicaService'

interface Props {
  historiaClinicaId: number
}

function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

function ConsultaItem({ consulta }: { consulta: ConsultaMedica }) {
  const [abierta, setAbierta] = useState(false)

  return (
    <Card withBorder radius="md" p="sm">
      <Stack gap="xs">
        <Group justify="space-between" wrap="nowrap">
          <Group gap="xs">
            <ThemeIcon size="sm" color="blue" variant="light">
              <IconStethoscope size={12} />
            </ThemeIcon>
            <Text size="sm" fw={500}>
              {formatFecha(consulta.fecha_consulta)}
            </Text>
            {consulta.tipo_atencion && (
              <Badge size="xs" variant="light" color="gray">
                {consulta.tipo_atencion.replace('_', ' ')}
              </Badge>
            )}
            {consulta.tipo_diagnostico && (
              <Badge
                size="xs"
                variant="light"
                color={consulta.tipo_diagnostico === 'definitivo'
                  ? 'emerald' : 'orange'}
              >
                {consulta.tipo_diagnostico}
              </Badge>
            )}
          </Group>
          <Button
            size="compact-xs"
            variant="subtle"
            color="gray"
            rightSection={abierta
              ? <IconChevronUp size={12} />
              : <IconChevronDown size={12} />}
            onClick={() => setAbierta(v => !v)}
          >
            {abierta ? 'Cerrar' : 'Ver detalle'}
          </Button>
        </Group>

        <Text size="xs" c="dimmed">
          Dr. {consulta.medico?.nombre_completo ?? '—'}
        </Text>

        <Collapse expanded={abierta}>
          <Stack gap="xs" pt="xs">
            {consulta.motivo_consulta && (
              <Stack gap={2}>
                <Text size="xs" fw={500} c="dimmed" tt="uppercase">
                  Motivo
                </Text>
                <Text size="xs">{consulta.motivo_consulta}</Text>
              </Stack>
            )}

            {consulta.enfermedad_actual && (
              <Stack gap={2}>
                <Text size="xs" fw={500} c="dimmed" tt="uppercase">
                  Enfermedad actual
                </Text>
                <Text size="xs">{consulta.enfermedad_actual}</Text>
              </Stack>
            )}

            {consulta.examen_fisico && (
              <Stack gap={2}>
                <Text size="xs" fw={500} c="dimmed" tt="uppercase">
                  Examen físico
                </Text>
                <Text size="xs">{consulta.examen_fisico}</Text>
              </Stack>
            )}

            <Stack gap={2}>
              <Text size="xs" fw={500} c="dimmed" tt="uppercase">
                Diagnóstico
              </Text>
              <Text size="xs">{consulta.diagnostico_detallado}</Text>
            </Stack>

            {consulta.plan_tratamiento && (
              <Stack gap={2}>
                <Text size="xs" fw={500} c="dimmed" tt="uppercase">
                  Plan de tratamiento
                </Text>
                <Text size="xs">{consulta.plan_tratamiento}</Text>
              </Stack>
            )}

            {consulta.notas_medico && (
              <Stack gap={2}>
                <Text size="xs" fw={500} c="dimmed" tt="uppercase">
                  Notas
                </Text>
                <Text size="xs">{consulta.notas_medico}</Text>
              </Stack>
            )}
          </Stack>
        </Collapse>
      </Stack>
    </Card>
  )
}

export function TabHistorial({ historiaClinicaId }: Props) {
  const { data: consultas = [], isLoading } = useQuery({
    queryKey: ['consultas', 'historial', historiaClinicaId],
    queryFn:  () =>
      consultaMedicaService.listarPorHistoria(historiaClinicaId),
    enabled:  !!historiaClinicaId,
    staleTime: 1000 * 30,
  })

  if (isLoading) {
    return (
      <Stack gap="sm" p="md">
        <Skeleton height={80} radius="md" />
        <Skeleton height={80} radius="md" />
      </Stack>
    )
  }

  if (consultas.length === 0) {
    return (
      <Stack p="md">
        <EmptyState
          icon={IconStethoscope}
          title="Sin consultas previas"
          description="Este paciente no tiene
            consultas anteriores registradas."
        />
      </Stack>
    )
  }

  return (
    <Stack gap="sm" p="md">
      <Text size="xs" c="dimmed">
        {consultas.length} consulta{consultas.length !== 1 ? 's' : ''}
        {' '}registrada{consultas.length !== 1 ? 's' : ''}
      </Text>
      {consultas.map((consulta) => (
        <ConsultaItem key={consulta.id} consulta={consulta} />
      ))}
    </Stack>
  )
}
