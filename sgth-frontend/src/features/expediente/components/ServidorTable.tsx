'use client'

import { PAGINACION_ES, SgthTable } from '@/components/ui'
import { getServidorColumns } from './servidor.columns'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  data: ServidorConRelaciones[]
  isLoading: boolean
  total: number
  page: number
  onPageChange: (page: number) => void
  /** Abrir el expediente del servidor. */
  onView: (servidor: ServidorConRelaciones) => void
  /** La selección solo se ofrece a quien puede pedir certificaciones médicas. */
  seleccionable?: boolean
  selectedRecords?: ServidorConRelaciones[]
  onSelectedRecordsChange?: (records: ServidorConRelaciones[]) => void
}

export function ServidorTable({
  data, isLoading, total, page,
  onPageChange, onView,
  seleccionable = false, selectedRecords, onSelectedRecordsChange,
}: Props) {
  return (
    <SgthTable
      {...PAGINACION_ES}
      records={data}
      columns={getServidorColumns({ onView })}
      fetching={isLoading}
      totalRecords={total || data.length || 0}
      recordsPerPage={15}
      page={page}
      onPageChange={onPageChange}
      minHeight={200}
      // Abrir el expediente es lo que se hace en nueve de cada diez visitas:
      // no debería exigir pasar por el menú de la fila.
      onRowClick={({ record }) => onView(record)}
      selectedRecords={seleccionable ? selectedRecords : undefined}
      onSelectedRecordsChange={seleccionable ? onSelectedRecordsChange : undefined}
    />
  )
}
