'use client'

import { SgthTable } from '@/components/ui/SgthTable'
import { useTriajesPendientes } from '../hooks/useTriaje'
import { useCancelarTurno } from '../hooks/useAgenda'
import { getTurnosColumns } from './turnos.columns'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  onSeleccionar: (turno: AgendaMedica) => void
}

export function TriajePendientesList({ onSeleccionar }: Props) {
  const { data: turnos = [], isLoading } = useTriajesPendientes()
  const cancelar = useCancelarTurno()

  return (
    <SgthTable
      records={turnos}
      columns={getTurnosColumns({
        onTomarTriaje: onSeleccionar,
        onCancelar: (id) => {
          if (confirm('¿Cancelar este turno?')) {
            cancelar.mutate(id)
          }
        },
      })}
      fetching={isLoading}
      minHeight={200}
      noRecordsText="Sin pacientes pendientes de triaje"
    />
  )
}
