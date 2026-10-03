'use client'

import { IconHeartbeat } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { useTriajesPendientes } from '../hooks/useTriaje'
import { getTurnosColumns } from './turnos.columns'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  /** Sin él no se ofrece «Tomar triaje»: quien no puede registrarlo no lo ve. */
  onSeleccionar?: (turno: AgendaMedica) => void
  onCancelar:     (id: number) => void
}

/** Los turnos de hoy que esperan sus signos vitales, del primero en llegar al último. */
export function TriajePendientesList({ onSeleccionar, onCancelar }: Props) {
  const { data: turnos = [], isLoading, error, refetch, dataUpdatedAt } = useTriajesPendientes()

  return (
    <DataState
      loading={isLoading}
      error={error}
      errorTitle="No se pudieron cargar los pendientes de triaje"
      errorHint="Esto no significa que nadie esté esperando."
      onRetry={() => refetch()}
      empty={!turnos.length}
      emptyProps={{
        icon: IconHeartbeat,
        title: 'Sin pacientes pendientes de triaje',
        description: 'Los turnos de hoy ya tienen sus signos vitales.',
      }}
    >
      <SgthTable
        records={turnos}
        columns={getTurnosColumns({ onTomarTriaje: onSeleccionar, onCancelar, ahora: dataUpdatedAt })}
        minHeight={200}
        // Las acciones a la vista también en el teléfono: antes había que
        // desplazar la tabla a lo ancho para llegar a «Tomar triaje».
        pinLastColumn
      />
    </DataState>
  )
}
