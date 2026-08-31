'use client'

import {
  Drawer, Stack, Text, Group, Badge,
  TextInput, ActionIcon, Card,
  Skeleton, Divider, Switch, ThemeIcon,
} from '@mantine/core'
import {
  IconPlus, IconTrash, IconBriefcase,
  IconGripVertical,
} from '@tabler/icons-react'
import { useState } from 'react'
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
  arrayMove,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import {
  usePuestoActividades,
  useCrearActividad,
  useEliminarActividad,
  useActualizarActividad,
} from '../hooks/usePuestoActividad'
import { puestoActividadService } from '../services/puestoActividadService'
import { useQueryClient } from '@tanstack/react-query'
import type { PuestoConRelaciones } from '@/types/api'
import type { PuestoActividad } from '../services/puestoActividadService'

interface Props {
  opened:  boolean
  onClose: () => void
  puesto:  PuestoConRelaciones | null
}

interface SortableItemProps {
  actividad:   PuestoActividad
  index:       number
  puestoId:    number
  onEliminar:  (id: number) => void
  onToggle:    (id: number, activo: boolean) => void
}

function SortableItem({
  actividad, index, onEliminar, onToggle,
}: SortableItemProps) {
  const {
    attributes, listeners, setNodeRef,
    transform, transition, isDragging,
  } = useSortable({ id: actividad.id })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity:    isDragging ? 0.5 : 1,
    zIndex:     isDragging ? 10 : undefined,
  }

  return (
    <div ref={setNodeRef} style={style}>
      <Card
        withBorder
        radius="md"
        p="sm"
        style={{
          opacity: actividad.activo ? 1 : 0.6,
          borderLeft: actividad.activo
            ? '3px solid var(--mantine-color-blue-6)'
            : '3px solid var(--mantine-color-gray-4)',
          cursor: isDragging ? 'grabbing' : 'default',
        }}
      >
        <Group justify="space-between" wrap="nowrap">
          <Group gap="sm" wrap="nowrap">
            <ThemeIcon
              size="xs"
              variant="subtle"
              color="gray"
              style={{ cursor: 'grab', touchAction: 'none' }}
              {...attributes}
              {...listeners}
            >
              <IconGripVertical size={12} />
            </ThemeIcon>
            <Text
              size="xs"
              c="dimmed"
              fw={600}
              style={{ minWidth: 20 }}
            >
              {index + 1}.
            </Text>
            <Text size="sm" style={{ flex: 1 }}>
              {actividad.descripcion}
            </Text>
          </Group>
          <Group gap="xs" wrap="nowrap">
            <Switch
              size="xs"
              checked={actividad.activo}
              onChange={(e) =>
                onToggle(actividad.id, e.currentTarget.checked)
              }
            />
            <ActionIcon
              size="sm"
              color="red"
              variant="subtle"
              onClick={() => {
                if (confirm('¿Eliminar esta actividad?')) {
                  onEliminar(actividad.id)
                }
              }}
            >
              <IconTrash size={13} />
            </ActionIcon>
          </Group>
        </Group>
      </Card>
    </div>
  )
}

export function PuestoActividadesDrawer({
  opened, onClose, puesto,
}: Props) {
  const contained     = useContainedInput()
  const { isMobile }  = useMobileBreakpoint()
  const [nueva, setNueva] = useState('')
  const qc = useQueryClient()

  const puestoId = puesto?.id ? Number(puesto.id) : null

  const { data: actividades = [], isLoading } =
    usePuestoActividades(puestoId)
  const crear      = useCrearActividad(puestoId ?? 0)
  const eliminar   = useEliminarActividad(puestoId ?? 0)
  const actualizar = useActualizarActividad(puestoId ?? 0)

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  )

  const handleAgregar = () => {
    if (!nueva.trim() || !puestoId) return
    crear.mutate(nueva.trim(), {
      onSuccess: () => setNueva(''),
    })
  }

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event
    if (!over || active.id === over.id || !puestoId) return

    const oldIndex = actividades.findIndex(a => a.id === active.id)
    const newIndex = actividades.findIndex(a => a.id === over.id)
    if (oldIndex === -1 || newIndex === -1) return

    const reordenadas = arrayMove(actividades, oldIndex, newIndex)

    qc.setQueryData(
      ['puesto-actividades', puestoId],
      reordenadas
    )

    try {
      await puestoActividadService.reordenar(
        puestoId,
        reordenadas.map(a => a.id)
      )
    } catch {
      qc.invalidateQueries({
        queryKey: ['puesto-actividades', puestoId],
      })
    }
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
              {(puesto as PuestoConRelaciones & { grupo_ocupacional?: { grupo?: string } })
                ?.grupo_ocupacional?.grupo && (
                <Badge size="sm" variant="light" color="emerald">
                  {(puesto as PuestoConRelaciones & { grupo_ocupacional?: { grupo?: string } })
                    .grupo_ocupacional?.grupo}
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
            Arrastra las actividades para reordenarlas.
            Se usan en el formulario FEMO para marcar
            los factores de riesgo del puesto.
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
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragEnd={handleDragEnd}
            >
              <SortableContext
                items={actividades.map(a => a.id)}
                strategy={verticalListSortingStrategy}
              >
                <Stack gap="xs">
                  {actividades.map((act, i) => (
                    <SortableItem
                      key={act.id}
                      actividad={act}
                      index={i}
                      puestoId={puestoId ?? 0}
                      onEliminar={(id) => eliminar.mutate(id)}
                      onToggle={(id, activo) =>
                        actualizar.mutate({ id, data: { activo } })
                      }
                    />
                  ))}
                </Stack>
              </SortableContext>
            </DndContext>
          )}
        </Stack>
      </Stack>
    </Drawer>
  )
}
