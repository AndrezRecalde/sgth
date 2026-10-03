'use client'

import { IconListCheck } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { getTurnosColumns } from './turnos.columns'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  turnos:         AgendaMedica[]
  isLoading:      boolean
  error:          unknown
  onReintentar:   () => void
  /** Cuándo llegaron los datos: la espera se mide contra eso y no contra un reloj. */
  ahora:          number
  vacio:          { titulo: string; descripcion: string }
  onCancelar?:    (id: number) => void
  onTomarTriaje?: (turno: AgendaMedica) => void
}

/**
 * La cola del día, del que llegó primero al último.
 *
 * Ya no resalta la primera fila: con el orden anterior (del más nuevo al más
 * viejo) era «el último en llegar», un dato que no ayudaba a decidir a quién
 * atender y que nadie explicaba en pantalla.
 */
export function ColaTurnosTable({
  turnos, isLoading, error, onReintentar, ahora, vacio, onCancelar, onTomarTriaje,
}: Props) {
  return (
    <DataState
      loading={isLoading}
      error={error}
      errorTitle="No se pudo cargar la cola"
      errorHint="Esto no significa que no haya pacientes esperando."
      onRetry={onReintentar}
      empty={!turnos.length}
      emptyProps={{ icon: IconListCheck, title: vacio.titulo, description: vacio.descripcion }}
    >
      <SgthTable
        records={turnos}
        columns={getTurnosColumns({ onCancelar, onTomarTriaje, ahora })}
        minHeight={200}
        // Las acciones a la vista también en el teléfono: antes había que
        // desplazar la tabla a lo ancho para llegar a «Tomar triaje».
        pinLastColumn
      />
    </DataState>
  )
}
