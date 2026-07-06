'use client'

import {
  Card, Stack, Group, Text, Avatar,
  Badge, Divider, Skeleton, ThemeIcon,
  ActionIcon, Textarea, Button,
  Collapse,
} from '@mantine/core'
import {
  IconUser, IconUsers, IconAlertTriangle,
  IconPlus, IconChevronDown, IconChevronUp,
} from '@tabler/icons-react'
import { useState } from 'react'
import { useContextoConsulta } from '../hooks/useContextoConsulta'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { AgendaMedica } from '../services/agendaService'
import type { Triaje } from '../services/triajeService'

interface Props {
  turno:             AgendaMedica
  historiaClinicaId: number
}

const SEVERIDAD_COLORS: Record<string, string> = {
  leve:     'yellow',
  moderada: 'orange',
  grave:    'red',
}

function CampoTriaje({ label, valor }: { label: string; valor?: string | number | null }) {
  if (!valor && valor !== 0) return null
  return (
    <Group justify="space-between" py={1}>
      <Text size="xs" c="dimmed">{label}</Text>
      <Text size="xs" fw={500}>{valor}</Text>
    </Group>
  )
}

export function PanelContextoPaciente({
  turno, historiaClinicaId,
}: Props) {
  const contained = useContainedInput()
  const { data: contexto, isLoading } = useContextoConsulta(
    historiaClinicaId, turno.id
  )
  const [notasAbiertas, setNotasAbiertas] = useState(false)

  const esServidor = !!turno.servidor_id
  const nombrePaciente = esServidor
    ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
    : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

  if (isLoading) {
    return (
      <Card withBorder radius="lg" p="md" h="100%">
        <Skeleton height={300} radius="md" />
      </Card>
    )
  }

  const triaje    = contexto?.triaje_actual as Triaje | null | undefined
  const alergias  = contexto?.historia_clinica.alergias ?? []
  const antecedentesPersonales = (contexto?.historia_clinica.antecedentes ?? [])
    .filter(a => a.tipo !== 'familiar')
  const antecedentesFamiliares = (contexto?.historia_clinica.antecedentes ?? [])
    .filter(a => a.tipo === 'familiar')
  const hayAlergiaGrave = alergias.some(a => a.severidad === 'grave')

  return (
    <Card withBorder radius="lg" p="md" h="100%">
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
            <Text size="sm" fw={600} lineClamp={1}>
              {nombrePaciente.trim() || '—'}
            </Text>
            <Text size="xs" c="dimmed" ff="monospace">
              {turno.folio}
            </Text>
          </Stack>
        </Group>

        {hayAlergiaGrave && (
          <Card
            withBorder
            radius="sm"
            p="xs"
            style={{
              borderColor: 'var(--mantine-color-red-6)',
              backgroundColor: 'var(--mantine-color-red-light)',
            }}
          >
            <Group gap="xs">
              <IconAlertTriangle
                size={14}
                color="var(--mantine-color-red-6)"
              />
              <Text size="xs" fw={600} c="red">
                ⚠ Alergia grave detectada
              </Text>
            </Group>
            {alergias.filter(a => a.severidad === 'grave').map(a => (
              <Text key={a.id} size="xs" c="red" ml="xs">
                · {a.descripcion}
              </Text>
            ))}
          </Card>
        )}

        {triaje && (
          <>
            <Divider
              label={<Text size="xs" fw={600} tt="uppercase" c="dimmed">Triaje</Text>}
              labelPosition="left"
            />
            <Stack gap={0}>
              <CampoTriaje label="Peso"          valor={triaje.peso_kg ? `${triaje.peso_kg} kg` : null} />
              <CampoTriaje label="Talla"         valor={triaje.talla_cm ? `${triaje.talla_cm} cm` : null} />
              <CampoTriaje label="IMC"           valor={triaje.imc} />
              <CampoTriaje label="P. arterial"   valor={triaje.presion_sistolica ? `${triaje.presion_sistolica}/${triaje.presion_diastolica}` : null} />
              <CampoTriaje label="F. cardíaca"   valor={triaje.frecuencia_cardiaca ? `${triaje.frecuencia_cardiaca} bpm` : null} />
              <CampoTriaje label="F. respiratoria" valor={triaje.frecuencia_respiratoria ? `${triaje.frecuencia_respiratoria} rpm` : null} />
              <CampoTriaje label="Temperatura"   valor={triaje.temperatura_c ? `${triaje.temperatura_c} °C` : null} />
              <CampoTriaje label="Sat. O2"       valor={triaje.saturacion_oxigeno ? `${triaje.saturacion_oxigeno}%` : null} />
            </Stack>
          </>
        )}

        <Divider
          label={
            <Group gap={4}>
              <Text size="xs" fw={600} tt="uppercase" c="dimmed">Alergias</Text>
              <ActionIcon size="xs" variant="subtle" color="blue">
                <IconPlus size={10} />
              </ActionIcon>
            </Group>
          }
          labelPosition="left"
        />
        {alergias.length === 0 ? (
          <Text size="xs" c="dimmed">Ninguna registrada</Text>
        ) : (
          <Stack gap={3}>
            {alergias.map((a) => (
              <Group key={a.id} gap={5} wrap="nowrap">
                <Badge
                  size="xs"
                  variant="light"
                  color={SEVERIDAD_COLORS[a.severidad] ?? 'gray'}
                >
                  {a.severidad}
                </Badge>
                <Text size="xs" lineClamp={1}>{a.descripcion}</Text>
              </Group>
            ))}
          </Stack>
        )}

        <Divider
          label={
            <Group gap={4}>
              <Text size="xs" fw={600} tt="uppercase" c="dimmed">Antecedentes personales</Text>
              <ActionIcon size="xs" variant="subtle" color="blue">
                <IconPlus size={10} />
              </ActionIcon>
            </Group>
          }
          labelPosition="left"
        />
        {antecedentesPersonales.length === 0 ? (
          <Text size="xs" c="dimmed">Ninguno registrado</Text>
        ) : (
          <Stack gap={3}>
            {antecedentesPersonales.map((a) => (
              <Text key={a.id} size="xs">
                <Text span fw={500} c="dimmed">{a.tipo}: </Text>
                {a.descripcion}
              </Text>
            ))}
          </Stack>
        )}

        <Divider
          label={
            <Group gap={4}>
              <Text size="xs" fw={600} tt="uppercase" c="dimmed">Antecedentes familiares</Text>
              <ActionIcon size="xs" variant="subtle" color="blue">
                <IconPlus size={10} />
              </ActionIcon>
            </Group>
          }
          labelPosition="left"
        />
        {antecedentesFamiliares.length === 0 ? (
          <Text size="xs" c="dimmed">Ninguno registrado</Text>
        ) : (
          <Stack gap={3}>
            {antecedentesFamiliares.map((a) => (
              <Text key={a.id} size="xs">
                <Text span fw={500} c="dimmed">{a.tipo}: </Text>
                {a.descripcion}
              </Text>
            ))}
          </Stack>
        )}

        <Divider
          label={
            <Group gap={4} style={{ cursor: 'pointer' }}
              onClick={() => setNotasAbiertas(v => !v)}
            >
              <Text size="xs" fw={600} tt="uppercase" c="dimmed">
                Notas del médico
              </Text>
              {notasAbiertas
                ? <IconChevronUp size={10} />
                : <IconChevronDown size={10} />}
            </Group>
          }
          labelPosition="left"
        />
        <Collapse expanded={notasAbiertas}>
          <Textarea
            placeholder="Notas adicionales durante la consulta..."
            autosize
            minRows={2}
            maxRows={4}
            {...contained}
          />
        </Collapse>
      </Stack>
    </Card>
  )
}
