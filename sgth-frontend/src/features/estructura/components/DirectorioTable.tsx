'use client'

import { DataTable } from 'mantine-datatable'
import { Box } from '@mantine/core'
import type { ExtensionTelefonica } from '@/types/api'
import { directorioColumns } from './directorio.columns'

interface DirectorioTableProps {
  data: ExtensionTelefonica[]
  isLoading: boolean
}

export function DirectorioTable({ data, isLoading }: DirectorioTableProps) {
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
        columns={directorioColumns}
        noRecordsText="No se encontraron extensiones telefónicas"
        minHeight={150}
      />
    </Box>
  )
}
