'use client'

import { Box } from '@mantine/core'
import { DataTable } from 'mantine-datatable'
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
}

export function ServidorTable({
  data, isLoading, total, page,
  onPageChange, onView, onEdit,
}: Props) {
  return (
    <Box style={{ overflowX: 'auto' }}>
      <DataTable
        records={data}
        columns={getServidorColumns({ onView, onEdit })}
        fetching={isLoading}
        totalRecords={total}
        recordsPerPage={15}
        page={page}
        onPageChange={onPageChange}
        withTableBorder
        borderRadius="md"
        highlightOnHover
        striped
        noRecordsText="No hay servidores registrados"
        minHeight={200}
      />
    </Box>
  )
}
