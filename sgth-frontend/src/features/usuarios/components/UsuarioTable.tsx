'use client'

import { SgthTable } from '@/components/ui/SgthTable'
import { getUsuarioColumns } from './usuario.columns'
import type { Usuario } from '@/types/api'

interface Props {
  data:        Usuario[]
  isLoading:   boolean
  total:       number
  page:        number
  onPageChange:          (page: number) => void
  onEdit:                (u: Usuario) => void
  onToggleActivo:        (u: Usuario) => void
  onRestablecerPassword: (u: Usuario) => void
  onPermisos:            (u: Usuario) => void
  onDesvincular:         (u: Usuario) => void
  onAsignarServidor:     (u: Usuario) => void
}

export function UsuarioTable({
  data, isLoading, total, page,
  onPageChange, onEdit,
  onToggleActivo, onRestablecerPassword,
  onPermisos, onDesvincular, onAsignarServidor,
}: Props) {
  return (
    <SgthTable
      records={data}
      columns={getUsuarioColumns({
        onEdit,
        onToggleActivo,
        onRestablecerPassword,
        onPermisos,
        onDesvincular,
        onAsignarServidor,
      })}
      fetching={isLoading}
      totalRecords={total || data.length || 0}
      recordsPerPage={15}
      page={page}
      onPageChange={onPageChange}
      minHeight={200}
    />
  )
}
