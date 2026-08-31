'use client'

import { confirmar } from '@/components/ui'
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
        onCancelar: (id) => confirmar({
          title:   'Cancelar turno',
          message: 'Se cancelará este turno y el paciente saldrá de la cola.',
          destructiva: true,
          confirmLabel: 'Cancelar turno',
          cancelLabel:  'Volver',
          onConfirm: () => cancelar.mutate(id),
        }),
      })}
      fetching={isLoading}
      minHeight={200}
      noRecordsText="Sin pacientes pendientes de triaje"
    />
  )
}
