'use client'

import { useState } from 'react'
import { ActionIcon, Button, Card, Group, Skeleton, Stack, Switch, Text, ThemeIcon } from '@mantine/core'
import { IconEdit, IconGripVertical, IconPlus, IconTrash } from '@tabler/icons-react'
import { useQueryClient } from '@tanstack/react-query'
import {
  DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy, arrayMove,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { confirmar, SectionHeading, StatusBadge } from '@/components/ui'
import type { PuestoActividad } from '@/types/api'
import { etiquetaFrecuencia } from '../constants/perfilPuesto'
import { usePuestoActividades, useEliminarActividad, useActualizarActividad } from '../hooks/usePuestoActividad'
import { puestoActividadService } from '../services/puestoActividadService'
import { PuestoActividadModal } from './PuestoActividadModal'

interface ItemProps {
  actividad:   PuestoActividad
  index:       number
  soloLectura: boolean
  onEditar:    (actividad: PuestoActividad) => void
  onEliminar:  (id: number) => void
  onToggle:    (id: number, activo: boolean) => void
}

/** Lo que la asignación de responsabilidades necesita de la actividad, además de su nombre. */
const tieneLosTresDatos = (a: PuestoActividad) => !!a.producto && !!a.conocimientos && !!a.frecuencia

function ActividadItem({ actividad, index, soloLectura, onEditar, onEliminar, onToggle }: ItemProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } =
    useSortable({ id: actividad.id })

  return (
    <div
      ref={setNodeRef}
      style={{
        transform:  CSS.Transform.toString(transform),
        transition,
        opacity:    isDragging ? 0.5 : 1,
        zIndex:     isDragging ? 10 : undefined,
      }}
    >
      <Card
        withBorder
        radius="md"
        p="sm"
        style={{
          opacity:    actividad.activo ? 1 : 0.6,
          borderLeft: actividad.activo ? '3px solid var(--sgth-accent)' : '3px solid var(--sgth-border-strong)',
        }}
      >
        <Group justify="space-between" wrap="nowrap" align="flex-start">
          <Group gap="sm" wrap="nowrap" align="flex-start" style={{ flex: 1, minWidth: 0 }}>
            {!soloLectura && (
              <ThemeIcon
                size="xs"
                variant="subtle"
                mt={3}
                style={{ cursor: 'grab', touchAction: 'none' }}
                {...attributes}
                {...listeners}
              >
                <IconGripVertical size={12} />
              </ThemeIcon>
            )}
            <Text size="xs" c="dimmed" fw={600} mt={2} style={{ minWidth: 20 }}>{index + 1}.</Text>
            <Stack gap={4} style={{ flex: 1, minWidth: 0 }}>
              <Text size="sm" fw={500}>{actividad.descripcion}</Text>
              {actividad.producto && (
                <Text size="xs"><Text span c="dimmed">Producto: </Text>{actividad.producto}</Text>
              )}
              {actividad.conocimientos && (
                <Text size="xs"><Text span c="dimmed">Conocimientos: </Text>{actividad.conocimientos}</Text>
              )}
              <Group gap={6}>
                {actividad.frecuencia && <StatusBadge>{etiquetaFrecuencia(actividad.frecuencia)}</StatusBadge>}
                {/* Qué le falta lo explica el texto de arriba; aquí cabe en un teléfono. */}
                {!tieneLosTresDatos(actividad) && <StatusBadge tone="warning" variant="dot">Incompleta</StatusBadge>}
              </Group>
            </Stack>
          </Group>
          {!soloLectura && (
            <Group gap="xs" wrap="nowrap">
              <Switch
                size="xs"
                checked={actividad.activo}
                aria-label={actividad.activo ? 'Desactivar actividad' : 'Activar actividad'}
                onChange={(e) => onToggle(actividad.id, e.currentTarget.checked)}
              />
              <ActionIcon size="sm" variant="subtle" aria-label="Editar actividad" onClick={() => onEditar(actividad)}>
                <IconEdit size={13} />
              </ActionIcon>
              <ActionIcon
                size="sm"
                color="red"
                variant="subtle"
                aria-label="Eliminar actividad"
                onClick={() => confirmar({
                  title:   'Eliminar actividad',
                  message: <>Se eliminará la actividad <b>{actividad.descripcion}</b> del puesto. No se puede deshacer.</>,
                  destructiva: true,
                  onConfirm: () => onEliminar(actividad.id),
                })}
              >
                <IconTrash size={13} />
              </ActionIcon>
            </Group>
          )}
        </Group>
      </Card>
    </div>
  )
}

interface Props {
  puestoId:    number
  soloLectura: boolean
}

/** Las actividades esenciales del puesto, en su orden. */
export function PuestoActividadesPanel({ puestoId, soloLectura }: Props) {
  const qc = useQueryClient()
  const { data: actividades = [], isLoading } = usePuestoActividades(puestoId)
  const eliminar   = useEliminarActividad(puestoId)
  const actualizar = useActualizarActividad(puestoId)
  // La actividad se conserva al cerrar: si se vaciara, el título pasaría a
  // «Nueva actividad» durante la animación de salida.
  const [modal, setModal] = useState<{ abierto: boolean; actividad: PuestoActividad | null }>(
    { abierto: false, actividad: null },
  )
  const abrir = (actividad: PuestoActividad | null) => setModal({ abierto: true, actividad })

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const alSoltar = async ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) return
    const desde = actividades.findIndex(a => a.id === active.id)
    const hasta = actividades.findIndex(a => a.id === over.id)
    if (desde === -1 || hasta === -1) return

    const reordenadas = arrayMove(actividades, desde, hasta)
    qc.setQueryData(['puesto-actividades', puestoId], reordenadas)
    try {
      await puestoActividadService.reordenar(puestoId, reordenadas.map(a => a.id))
    } catch {
      qc.invalidateQueries({ queryKey: ['puesto-actividades', puestoId] })
    }
  }

  const completas = actividades.filter(tieneLosTresDatos).length

  return (
    <Stack gap="xs">
      <SectionHeading
        title="Actividades esenciales"
        action={!soloLectura && (
          <Button size="xs" variant="light" leftSection={<IconPlus size={14} />} onClick={() => abrir(null)}>
            Agregar
          </Button>
        )}
      />
      <Text size="xs" c="dimmed">
        {soloLectura ? '' : 'Arrastre las actividades para reordenarlas. '}
        La matriz de riesgos del FEMO usa la descripción; la asignación de responsabilidades
        de la evaluación del desempeño usa también el producto, los conocimientos y la frecuencia.
        {actividades.length > 0 && ` ${completas} de ${actividades.length} tienen esos tres datos; las demás están marcadas como incompletas.`}
      </Text>

      {isLoading ? (
        <Stack gap="xs">
          <Skeleton height={52} radius="md" />
          <Skeleton height={52} radius="md" />
        </Stack>
      ) : actividades.length === 0 ? (
        <Card withBorder radius="md" p="md">
          <Text size="sm" c="dimmed" ta="center">
            No hay actividades registradas.{!soloLectura && ' Agregue la primera actividad del puesto.'}
          </Text>
        </Card>
      ) : (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={alSoltar}>
          <SortableContext items={actividades.map(a => a.id)} strategy={verticalListSortingStrategy}>
            <Stack gap="xs">
              {actividades.map((act, i) => (
                <ActividadItem
                  key={act.id}
                  actividad={act}
                  index={i}
                  soloLectura={soloLectura}
                  onEditar={abrir}
                  onEliminar={(id) => eliminar.mutate(id)}
                  onToggle={(id, activo) => actualizar.mutate({ id, data: { activo } })}
                />
              ))}
            </Stack>
          </SortableContext>
        </DndContext>
      )}

      {!soloLectura && (
        <PuestoActividadModal
          opened={modal.abierto}
          onClose={() => setModal(m => ({ ...m, abierto: false }))}
          puestoId={puestoId}
          actividad={modal.actividad}
        />
      )}
    </Stack>
  )
}
