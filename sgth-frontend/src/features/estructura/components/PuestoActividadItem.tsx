'use client'

import { ActionIcon, Card, Group, Stack, Switch, Text, ThemeIcon } from '@mantine/core'
import { IconEdit, IconGripVertical, IconTrash } from '@tabler/icons-react'
import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { confirmar, StatusBadge } from '@/components/ui'
import type { PuestoActividad } from '@/types/api'
import { etiquetaFrecuencia } from '../constants/perfilPuesto'

interface ItemProps {
  actividad:   PuestoActividad
  index:       number
  soloLectura: boolean
  onEditar:    (actividad: PuestoActividad) => void
  onEliminar:  (id: number) => void
  onToggle:    (id: number, activo: boolean) => void
}

/** Lo que la asignación de responsabilidades necesita de la actividad, además de su nombre. */
export const tieneLosTresDatos = (a: PuestoActividad) => !!a.producto && !!a.conocimientos && !!a.frecuencia

export function PuestoActividadItem({ actividad, index, soloLectura, onEditar, onEliminar, onToggle }: ItemProps) {
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
