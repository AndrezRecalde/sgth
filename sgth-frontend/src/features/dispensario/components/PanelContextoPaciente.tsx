'use client'

import {
  Card, Stack, Group, Text, Avatar,
  Badge, Divider, Skeleton, ThemeIcon,
} from '@mantine/core'
import {
  IconUser, IconUsers, IconAlertTriangle,
  IconHistory,
} from '@tabler/icons-react'
import { useContextoConsulta } from '../hooks/useContextoConsulta'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  turno: AgendaMedica
  historiaClinicaId: number
}

const SEVERIDAD_COLORS: Record<string, string> = {
  leve:     'yellow',
  moderada: 'orange',
  grave:    'red',
}

function formatFecha(fecha?: string | null): string {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

export function PanelContextoPaciente({
  turno, historiaClinicaId,
}: Props) {
  const { data: contexto, isLoading } = useContextoConsulta(
    historiaClinicaId, turno.id
  )

  const esServidor = !!turno.servidor_id
  const nombrePaciente = esServidor
    ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
    : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

  if (isLoading) {
    return (
      <Card withBorder radius="lg" p="md">
        <Skeleton height={200} radius="md" />
      </Card>
    )
  }

  const triaje = contexto?.triaje_actual
  const alergias = contexto?.historia_clinica.alergias ?? []
  const antecedentes = contexto?.historia_clinica.antecedentes ?? []
  const consultasAnteriores = contexto?.consultas_anteriores ?? []

  return (
    <Card withBorder radius="lg" p="md">
      <Stack gap="sm">
        <Group gap="xs" wrap="nowrap">
          <Avatar
            color={esServidor ? 'emerald' : 'blue'}
            radius="xl"
            size="sm"
          >
            {esServidor ? <IconUser size={14} /> : <IconUsers size={14} />}
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

        {triaje && (
          <>
            <Divider
              label={
                <Text size="xs" fw={600} tt="uppercase" c="dimmed">
                  Triaje
                </Text>
              }
              labelPosition="left"
              mt="xs"
            />
            <Stack gap={2}>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">Peso</Text>
                <Text size="xs" fw={500}>{triaje.peso_kg} kg</Text>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">IMC</Text>
                <Text size="xs" fw={500}>{triaje.imc ?? '—'}</Text>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">P. arterial</Text>
                <Text size="xs" fw={500}>
                  {triaje.presion_sistolica}/{triaje.presion_diastolica}
                </Text>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">Sat. O2</Text>
                <Text size="xs" fw={500}>{triaje.saturacion_oxigeno}%</Text>
              </Group>
            </Stack>
          </>
        )}

        <Divider
          label={
            <Group gap={4}>
              <IconAlertTriangle size={12} />
              <Text size="xs" fw={600} tt="uppercase" c="dimmed">
                Alergias
              </Text>
            </Group>
          }
          labelPosition="left"
          mt="xs"
        />
        {alergias.length === 0 ? (
          <Text size="xs" c="dimmed">Ninguna registrada</Text>
        ) : (
          <Stack gap={4}>
            {alergias.map((a) => (
              <Group key={a.id} gap={6} wrap="nowrap">
                <Badge
                  size="xs"
                  variant="light"
                  color={SEVERIDAD_COLORS[a.severidad] ?? 'gray'}
                >
                  {a.severidad}
                </Badge>
                <Text size="xs">{a.descripcion}</Text>
              </Group>
            ))}
          </Stack>
        )}

        <Divider
          label={
            <Text size="xs" fw={600} tt="uppercase" c="dimmed">
              Antecedentes
            </Text>
          }
          labelPosition="left"
          mt="xs"
        />
        {antecedentes.length === 0 ? (
          <Text size="xs" c="dimmed">Ninguno registrado</Text>
        ) : (
          <Stack gap={4}>
            {antecedentes.map((a) => (
              <Text key={a.id} size="xs">
                <Text span fw={500} c="dimmed">{a.tipo}:</Text>
                {' '}{a.descripcion}
              </Text>
            ))}
          </Stack>
        )}

        {consultasAnteriores.length > 0 && (
          <>
            <Divider
              label={
                <Group gap={4}>
                  <IconHistory size={12} />
                  <Text size="xs" fw={600} tt="uppercase" c="dimmed">
                    Consultas previas
                  </Text>
                </Group>
              }
              labelPosition="left"
              mt="xs"
            />
            <Stack gap={6}>
              {consultasAnteriores.map((c) => (
                <Stack key={c.id} gap={0}>
                  <Text size="xs" c="dimmed">
                    {formatFecha(c.fecha_consulta)} —{' '}
                    {c.medico?.nombre_completo ?? '—'}
                  </Text>
                </Stack>
              ))}
            </Stack>
          </>
        )}
      </Stack>
    </Card>
  )
}
