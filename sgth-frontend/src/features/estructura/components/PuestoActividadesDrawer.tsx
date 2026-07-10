'use client'

import {
  Drawer, Stack, Text, Group, Badge,
  TextInput, ActionIcon, Card,
  Button, Skeleton, Divider,
  Switch, ThemeIcon,
} from '@mantine/core'
import {
  IconPlus, IconTrash, IconBriefcase,
  IconCheck, IconGripVertical,
} from '@tabler/icons-react'
import { useState } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import {
  usePuestoActividades,
  useCrearActividad,
  useEliminarActividad,
  useActualizarActividad,
} from '../hooks/usePuestoActividad'
import type { PuestoConRelaciones } from '@/types/api'

interface Props {
  opened:  boolean
  onClose: () => void
  puesto:  PuestoConRelaciones | null
}

export function PuestoActividadesDrawer({
  opened, onClose, puesto,
}: Props) {
  const contained      = useContainedInput()
  const { isMobile }   = useMobileBreakpoint()
  const [nueva, setNueva] = useState('')

  const puestoId = puesto?.id ? Number(puesto.id) : null

  const { data: actividades = [], isLoading } =
    usePuestoActividades(puestoId)
  const crear     = useCrearActividad(puestoId ?? 0)
  const eliminar  = useEliminarActividad(puestoId ?? 0)
  const actualizar = useActualizarActividad(puestoId ?? 0)

  const handleAgregar = () => {
    if (!nueva.trim() || !puestoId) return
    crear.mutate(nueva.trim(), {
      onSuccess: () => setNueva(''),
    })
  }

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="blue" variant="light" size="md" radius="md">
            <IconBriefcase size={16} />
          </ThemeIcon>
          <Stack gap={0}>
            <Text fw={700} size="sm">
              {puesto?.cargo?.nombre ?? 'Puesto'}
            </Text>
            <Text size="xs" c="dimmed">
              Actividades del puesto
            </Text>
          </Stack>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 480}
      padding="lg"
    >
      <Stack gap="md">
        <Stack gap="xs">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase"
            style={{ letterSpacing: '0.05em' }}>
            Información del puesto
          </Text>
          <Card withBorder radius="md" p="sm">
            <Group gap="xs" wrap="wrap">
              {puesto?.cargo?.nombre && (
                <Badge size="sm" variant="light" color="blue">
                  {puesto.cargo.nombre}
                </Badge>
              )}
              {puesto?.unidad_administrativa?.nombre && (
                <Badge size="sm" variant="light" color="gray">
                  {puesto.unidad_administrativa.nombre}
                </Badge>
              )}
              {puesto?.grupo_ocupacional?.grupo && (
                <Badge size="sm" variant="light" color="emerald">
                  {puesto.grupo_ocupacional.grupo}
                </Badge>
              )}
              {puesto?.regimen_laboral && (
                <Badge size="sm" variant="outline" color="orange">
                  {puesto.regimen_laboral.toUpperCase()}
                </Badge>
              )}
            </Group>
          </Card>
        </Stack>

        <Divider />

        <Stack gap="xs">
          <Group justify="space-between">
            <Text size="xs" fw={600} c="dimmed" tt="uppercase"
              style={{ letterSpacing: '0.05em' }}>
              Actividades principales
            </Text>
            <Badge size="sm" variant="light" color="blue">
              {actividades.length} actividad{actividades.length !== 1 ? 'es' : ''}
            </Badge>
          </Group>

          <Text size="xs" c="dimmed">
            Las actividades se usan en el formulario FEMO
            para marcar los factores de riesgo del puesto.
          </Text>

          <Group gap="xs">
            <TextInput
              placeholder="Ej: Mantenimiento eléctrico"
              style={{ flex: 1 }}
              {...contained}
              value={nueva}
              onChange={(e) => setNueva(e.currentTarget.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') handleAgregar()
              }}
            />
            <ActionIcon
              color="emerald"
              size="lg"
              onClick={handleAgregar}
              loading={crear.isPending}
              disabled={!nueva.trim()}
            >
              <IconPlus size={16} />
            </ActionIcon>
          </Group>

          {isLoading ? (
            <Stack gap="xs">
              <Skeleton height={52} radius="md" />
              <Skeleton height={52} radius="md" />
              <Skeleton height={52} radius="md" />
            </Stack>
          ) : actividades.length === 0 ? (
            <Card withBorder radius="md" p="md">
              <Text size="sm" c="dimmed" ta="center">
                No hay actividades registradas.
                Agrega la primera actividad del puesto.
              </Text>
            </Card>
          ) : (
            <Stack gap="xs">
              {actividades.map((act, i) => (
                <Card
                  key={act.id}
                  withBorder
                  radius="md"
                  p="sm"
                  style={{
                    opacity: act.activo ? 1 : 0.6,
                    borderLeft: act.activo
                      ? '3px solid var(--mantine-color-blue-6)'
                      : '3px solid var(--mantine-color-gray-4)',
                  }}
                >
                  <Group justify="space-between" wrap="nowrap">
                    <Group gap="sm" wrap="nowrap">
                      <ThemeIcon
                        size="xs"
                        variant="subtle"
                        color="gray"
                        style={{ cursor: 'grab' }}
                      >
                        <IconGripVertical size={12} />
                      </ThemeIcon>
                      <Text
                        size="xs"
                        c="dimmed"
                        fw={600}
                        style={{ minWidth: 20 }}
                      >
                        {i + 1}.
                      </Text>
                      <Text size="sm" style={{ flex: 1 }}>
                        {act.descripcion}
                      </Text>
                    </Group>
                    <Group gap="xs" wrap="nowrap">
                      <Switch
                        size="xs"
                        checked={act.activo}
                        onChange={(e) =>
                          actualizar.mutate({
                            id: act.id,
                            data: { activo: e.currentTarget.checked },
                          })
                        }
                      />
                      <ActionIcon
                        size="sm"
                        color="red"
                        variant="subtle"
                        onClick={() => {
                          if (confirm('¿Eliminar esta actividad?')) {
                            eliminar.mutate(act.id)
                          }
                        }}
                      >
                        <IconTrash size={13} />
                      </ActionIcon>
                    </Group>
                  </Group>
                </Card>
              ))}
            </Stack>
          )}
        </Stack>
      </Stack>
    </Drawer>
  )
}
