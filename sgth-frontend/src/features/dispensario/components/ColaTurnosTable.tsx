'use client'

import { SgthTable } from '@/components/ui/SgthTable'
import { getTurnosColumns } from './turnos.columns'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  turnos:         AgendaMedica[]
  isLoading:      boolean
  onCancelar?:    (id: number) => void
  onTomarTriaje?: (turno: AgendaMedica) => void
}

export function ColaTurnosTable({
  turnos, isLoading, onCancelar, onTomarTriaje,
}: Props) {
  const masRecienteId = turnos[0]?.id

  return (
    <SgthTable
      records={turnos}
      columns={getTurnosColumns({ onCancelar, onTomarTriaje })}
      fetching={isLoading}
      minHeight={200}
      noRecordsText="Sin turnos en la cola para esta fecha"
      rowStyle={(turno) =>
        turno.id === masRecienteId
          ? {
              backgroundColor: 'var(--mantine-color-emerald-light)',
            }
          : undefined
      }
    />
  )
}
