'use client'

import { SgthTable } from '@/components/ui/SgthTable'
import { useTriajesPendientes } from '../hooks/useTriaje'
import { getTriajePendientesColumns } from './triajePendientes.columns'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  onSeleccionar: (turno: AgendaMedica) => void
}

export function TriajePendientesList({ onSeleccionar }: Props) {
  const { data: turnos = [], isLoading } = useTriajesPendientes()

  return (
    <SgthTable
      records={turnos}
      columns={getTriajePendientesColumns({ onSeleccionar })}
      fetching={isLoading}
      minHeight={200}
      noRecordsText="Sin pacientes pendientes de triaje"
    />
  )
}
