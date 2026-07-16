'use client'

import { SgthTable } from '@/components/ui/SgthTable'
import { getServidorColumns } from './servidor.columns'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  data: ServidorConRelaciones[]
  isLoading: boolean
  total: number
  page: number
  onPageChange: (page: number) => void
  onView: (servidor: ServidorConRelaciones) => void
  onEdit: (servidor: ServidorConRelaciones) => void
  selectedRecords?: ServidorConRelaciones[]
  onSelectedRecordsChange?: (records: ServidorConRelaciones[]) => void
}

export function ServidorTable({
  data, isLoading, total, page,
  onPageChange, onView, onEdit,
  selectedRecords, onSelectedRecordsChange,
}: Props) {
  return (
    <SgthTable
      records={data}
      columns={getServidorColumns({ onView, onEdit })}
      fetching={isLoading}
      totalRecords={total || data.length || 0}
      recordsPerPage={15}
      page={page}
      onPageChange={onPageChange}
      minHeight={200}
      selectedRecords={selectedRecords}
      onSelectedRecordsChange={onSelectedRecordsChange}
    />
  )
}
