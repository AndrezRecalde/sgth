'use client'

import { useState } from 'react'
import { Button, Card, Stack, Text } from '@mantine/core'
import { IconPlus } from '@tabler/icons-react'
import {
  DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy, arrayMove,
} from '@dnd-kit/sortable'
import { DataState, SectionHeading } from '@/components/ui'
import type { PuestoActividad } from '@/types/api'
import {
  usePuestoActividades, useEliminarActividad, useActualizarActividad, useReordenarActividades,
} from '../hooks/usePuestoActividad'
import { PuestoActividadItem, tieneLosTresDatos } from './PuestoActividadItem'
import { PuestoActividadModal } from './PuestoActividadModal'

interface Props {
  puestoId:    number
  soloLectura: boolean
}

/** Las actividades esenciales del puesto, en su orden. */
export function PuestoActividadesPanel({ puestoId, soloLectura }: Props) {
  const { data: actividades = [], isLoading, error, refetch } = usePuestoActividades(puestoId)
  const eliminar   = useEliminarActividad(puestoId)
  const actualizar = useActualizarActividad(puestoId)
  const reordenar  = useReordenarActividades(puestoId)
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

  const alSoltar = ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) return
    const desde = actividades.findIndex(a => a.id === active.id)
    const hasta = actividades.findIndex(a => a.id === over.id)
    if (desde === -1 || hasta === -1) return

    reordenar.mutate(arrayMove(actividades, desde, hasta))
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

      {/* Con el error a la vista: antes una consulta fallida decía «No hay
          actividades registradas», y alguien podía cargarlas otra vez. */}
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las actividades"
        onRetry={refetch}
        empty={false}
        skeletonRows={2}
      >
        {actividades.length === 0 ? (
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
                  <PuestoActividadItem
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
      </DataState>

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
