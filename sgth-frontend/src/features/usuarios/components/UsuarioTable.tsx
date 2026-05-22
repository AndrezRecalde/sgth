'use client'

import { Box } from '@mantine/core'
import { DataTable } from 'mantine-datatable'
import { getUsuarioColumns } from './usuario.columns'
import type { Usuario } from '@/types/api'

interface Props {
  data:        Usuario[]
  isLoading:   boolean
  total:       number
  page:        number
  onPageChange: (page: number) => void
  onEdit:                (u: Usuario) => void
  onToggleActivo:        (u: Usuario) => void
  onRestablecerPassword: (u: Usuario) => void
}

export function UsuarioTable({
  data, isLoading, total, page,
  onPageChange, onEdit,
  onToggleActivo, onRestablecerPassword,
}: Props) {
  return (
    <Box style={{ overflowX: 'auto' }}>
      <DataTable
        records={data}
        columns={getUsuarioColumns({
          onEdit,
          onToggleActivo,
          onRestablecerPassword,
        })}
        fetching={isLoading}
        totalRecords={total}
        recordsPerPage={15}
        page={page}
        onPageChange={onPageChange}
        withTableBorder
        borderRadius="md"
        highlightOnHover
        striped
        noRecordsText="No hay usuarios registrados"
        minHeight={200}
      />
    </Box>
  )
}
