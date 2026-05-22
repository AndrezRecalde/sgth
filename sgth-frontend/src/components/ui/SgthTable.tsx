'use client'

import { Box } from '@mantine/core'
import { DataTable } from 'mantine-datatable'
import type { DataTableProps } from 'mantine-datatable'

type SgthTableProps<T> = DataTableProps<T>

export function SgthTable<T = any>(
  props: SgthTableProps<T>
) {
  return (
    <Box style={{ overflowX: 'auto' }}>
      <DataTable
        withTableBorder
        withColumnBorders
        borderRadius="md"
        striped
        highlightOnHover
        verticalSpacing="sm"
        noRecordsText="No hay registros para mostrar"
        {...props}
      />
    </Box>
  )
}
