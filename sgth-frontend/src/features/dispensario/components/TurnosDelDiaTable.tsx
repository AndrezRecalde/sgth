'use client'

import {
  Card, Group, Text, Badge, Button,
  Stack, ActionIcon, Tooltip, Menu,
  ThemeIcon,
} from '@mantine/core'
import {
  IconStethoscope, IconUserOff,
  IconRefresh, IconDotsVertical,
  IconEye, IconClockHour4,
} from '@tabler/icons-react'
import { useTurnosDelDia, useAccionesTurno } from '../hooks/useAgenda'
import { EmptyState } from '@/components/ui/EmptyState'
import type { AgendaMedica, EstadoAgenda } from '../services/agendaService'

interface Props {
  onAtender:    (turno: AgendaMedica) => void
  onVerConsulta: (turno: AgendaMedica) => void
}

const ESTADO_CONFIG: Record<EstadoAgenda, {
  label:  string
  color:  string
}> = {
  en_espera:     { label: 'En espera',     color: 'gray'   },
  en_sala:       { label: 'En sala',       color: 'blue'   },
  en_consulta:   { label: 'En consulta',   color: 'blue'   },
  atendido:      { label: 'Atendido',      color: 'emerald'},
  no_presentado: { label: 'No se presentó',color: 'orange' },
  cancelado:     { label: 'Cancelado',     color: 'red'    },
}

function getNombrePaciente(turno: AgendaMedica): string {
  if (turno.servidor) {
    return `${turno.servidor.nombre} ${turno.servidor.apellido}`
  }
  if (turno.carga_familiar) {
    return `${turno.carga_familiar.nombres} ${turno.carga_familiar.apellidos}`
  }
  return '—'
}

function getTipoPaciente(turno: AgendaMedica): string {
  return turno.servidor_id ? 'Servidor' : 'Familiar'
}

export function TurnosDelDiaTable({ onAtender, onVerConsulta }: Props) {
  const { data: turnos = [], isLoading } = useTurnosDelDia()
  const { noPresentado, reactivar } = useAccionesTurno()

  const atendidos   = turnos.filter(t => t.estado === 'atendido').length
  const enEspera    = turnos.filter(t =>
    ['en_espera', 'en_sala', 'en_consulta'].includes(t.estado)
  ).length

  if (isLoading) {
    return (
      <Card withBorder radius="lg" p="md">
        <Text size="sm" c="dimmed">Cargando turnos...</Text>
      </Card>
    )
  }

  if (turnos.length === 0) {
    return (
      <EmptyState
        icon={IconStethoscope}
        title="Sin turnos para hoy"
        description="No tienes pacientes asignados para el día de hoy."
      />
    )
  }

  return (
    <Stack gap="sm">
      <Group justify="space-between">
        <Group gap="xs">
          <Badge size="sm" variant="light" color="emerald">
            {atendidos} atendido{atendidos !== 1 ? 's' : ''}
          </Badge>
          <Badge size="sm" variant="light" color="blue">
            {enEspera} en espera
          </Badge>
        </Group>
      </Group>

      {turnos.map((turno, i) => {
        const estado  = ESTADO_CONFIG[turno.estado]
          ?? { label: turno.estado, color: 'gray' }
        const nombre  = getNombrePaciente(turno)
        const tipo    = getTipoPaciente(turno)
        const esPendiente = ['en_espera', 'en_sala'].includes(turno.estado)
        const esAtendido  = turno.estado === 'atendido'
        const esNoPresentado = turno.estado === 'no_presentado'
        const enConsulta  = turno.estado === 'en_consulta'
        const tieneTriaje = !!turno.triaje
        const puedeAtender = (esPendiente || enConsulta) &&
          (tieneTriaje || !turno.requiere_triaje)

        return (
          <Card
            key={turno.id}
            withBorder
            radius="md"
            p="sm"
            style={{
              borderLeft: enConsulta
                ? '3px solid var(--mantine-color-blue-6)'
                : esAtendido
                  ? '3px solid var(--mantine-color-emerald-6)'
                  : '3px solid transparent',
              opacity: esNoPresentado ? 0.6 : 1,
            }}
          >
            <Group justify="space-between" wrap="nowrap">
              <Group gap="sm" wrap="nowrap">
                <ThemeIcon
                  size="md"
                  radius="xl"
                  variant="light"
                  color={esAtendido ? 'emerald'
                    : enConsulta ? 'blue' : 'gray'}
                >
                  <Text size="xs" fw={600}>{i + 1}</Text>
                </ThemeIcon>

                <Stack gap={0}>
                  <Text size="sm" fw={600}>{nombre}</Text>
                  <Group gap={4}>
                    <Text size="xs" c="dimmed">{tipo}</Text>
                    {turno.folio && (
                      <Text size="xs" c="dimmed" ff="monospace">
                        · {turno.folio}
                      </Text>
                    )}
                  </Group>
                </Stack>
              </Group>

              <Group gap="xs" wrap="nowrap">
                {!tieneTriaje && turno.requiere_triaje && !esAtendido && (
                  <Tooltip label="Sin triaje" withArrow>
                    <ThemeIcon
                      size="xs" color="orange" variant="light"
                    >
                      <IconClockHour4 size={11} />
                    </ThemeIcon>
                  </Tooltip>
                )}

                <Badge
                  size="sm"
                  variant="light"
                  color={estado.color}
                >
                  {estado.label}
                </Badge>

                {puedeAtender && (
                  <Button
                    size="xs"
                    color={enConsulta ? 'blue' : 'emerald'}
                    variant={enConsulta ? 'light' : 'filled'}
                    leftSection={<IconStethoscope size={12} />}
                    onClick={() => onAtender(turno)}
                  >
                    {enConsulta ? 'Continuar' : 'Atender'}
                  </Button>
                )}

                {esAtendido && (
                  <Button
                    size="xs"
                    variant="light"
                    color="blue"
                    leftSection={<IconEye size={12} />}
                    onClick={() => onVerConsulta(turno)}
                  >
                    Ver consulta
                  </Button>
                )}

                <Menu shadow="md" width={180}>
                  <Menu.Target>
                    <ActionIcon
                      size="sm" variant="subtle" color="gray"
                    >
                      <IconDotsVertical size={14} />
                    </ActionIcon>
                  </Menu.Target>
                  <Menu.Dropdown>
                    {esPendiente && (
                      <Menu.Item
                        color="orange"
                        leftSection={<IconUserOff size={13} />}
                        onClick={() => {
                          if (confirm('¿Marcar como no presentado?')) {
                            noPresentado.mutate(turno.id)
                          }
                        }}
                      >
                        No se presentó
                      </Menu.Item>
                    )}
                    {esNoPresentado && (
                      <Menu.Item
                        color="emerald"
                        leftSection={<IconRefresh size={13} />}
                        onClick={() => {
                          if (confirm('¿Reactivar este turno?')) {
                            reactivar.mutate(turno.id)
                          }
                        }}
                      >
                        Reactivar turno
                      </Menu.Item>
                    )}
                    {(esAtendido || enConsulta) && (
                      <Menu.Item
                        leftSection={<IconEye size={13} />}
                        onClick={() => onVerConsulta(turno)}
                      >
                        Ver consulta
                      </Menu.Item>
                    )}
                  </Menu.Dropdown>
                </Menu>
              </Group>
            </Group>
          </Card>
        )
      })}
    </Stack>
  )
}
