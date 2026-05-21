'use client'

import { DataTable } from 'mantine-datatable'
import { Box } from '@mantine/core'
import type { ExtensionConRelaciones } from '@/types/api'
import { getDirectorioColumns } from './directorio.columns'

interface DirectorioTableProps {
  data: ExtensionConRelaciones[]
  isLoading: boolean
  onEdit?: (record: ExtensionConRelaciones) => void
  onDelete?: (record: ExtensionConRelaciones) => void
}

export function DirectorioTable({ data, isLoading, onEdit, onDelete }: DirectorioTableProps) {
  const handlers = onEdit && onDelete ? { onEdit, onDelete } : undefined

  return (
    <Box style={{ overflowX: 'auto' }}>
      <DataTable
        withTableBorder
        borderRadius="md"
        withColumnBorders
        striped
        highlightOnHover
        records={data}
        fetching={isLoading}
        columns={getDirectorioColumns(handlers)}
        noRecordsText="No se encontraron extensiones telefónicas"
        minHeight={150}
      />
    </Box>
  )
}
