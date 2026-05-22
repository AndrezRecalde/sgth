'use client'

import { SgthTable } from '@/components/ui/SgthTable'
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
    <SgthTable
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
      minHeight={200}
    />
  )
}
