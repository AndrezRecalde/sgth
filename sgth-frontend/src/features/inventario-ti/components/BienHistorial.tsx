'use client'

import { Stack } from '@mantine/core'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useHistorialBien } from '../hooks/useBienes'
import { columnasCustodia, columnasMantenimiento } from './historial.columns'

/** Quién ha tenido el equipo y qué se le ha hecho, del más reciente al más antiguo. */
export function BienHistorial({ bienId }: { bienId: number }) {
  const { data, isLoading, error } = useHistorialBien(bienId)

  return (
    <DataState loading={isLoading} error={error}>
      <Stack gap="lg">
        <SectionCard title="Custodia">
          <SgthTable
            records={data?.asignaciones ?? []}
            columns={columnasCustodia}
            minHeight={120}
            noRecordsText="Nadie ha tenido este equipo en custodia."
          />
        </SectionCard>
        <SectionCard title="Mantenimientos">
          <SgthTable
            records={data?.mantenimientos ?? []}
            columns={columnasMantenimiento}
            minHeight={120}
            noRecordsText="El equipo no tiene mantenimientos registrados."
          />
        </SectionCard>
      </Stack>
    </DataState>
  )
}
