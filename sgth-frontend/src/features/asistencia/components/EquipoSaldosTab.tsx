'use client'

import { IconScale } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { getEquipoSaldosColumns } from './equipoSaldos.columns'
import { useSaldosDelEquipo } from '../hooks/useVacaciones'

const COLUMNAS = getEquipoSaldosColumns()

/** Cuántos días de vacaciones le quedan a cada persona de su equipo, para planificar. */
export function EquipoSaldosTab() {
  const { data = [], isLoading, error } = useSaldosDelEquipo()

  return (
    <DataState
      loading={isLoading}
      error={error}
      empty={!data.length}
      emptyProps={{
        icon: IconScale,
        title: 'Sin servidores en su unidad',
        description: 'Si alguien de su equipo no aparece, pida a Talento Humano que revise la unidad de su ficha.',
      }}
    >
      <SgthTable
        records={data}
        columns={COLUMNAS}
        idAccessor="servidor_id"
        minHeight={200}
      />
    </DataState>
  )
}
