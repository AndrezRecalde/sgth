'use client'

import { SgthTable } from '@/components/ui/SgthTable'
import { getColaTurnosColumns } from './colaTurnos.columns'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  turnos:      AgendaMedica[]
  isLoading:   boolean
  onCancelar?: (id: number) => void
}

export function ColaTurnosTable({
  turnos, isLoading, onCancelar,
}: Props) {
  return (
    <SgthTable
      records={turnos}
      columns={getColaTurnosColumns({
        onCancelar: onCancelar ?? (() => {}),
      })}
      fetching={isLoading}
      minHeight={200}
      noRecordsText="Sin turnos en la cola para esta fecha"
    />
  )
}
