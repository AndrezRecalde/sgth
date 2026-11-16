'use client'

import { SgthTable } from '@/components/ui/SgthTable'
import { useAuth } from '@/hooks/useAuth'
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
  const { usuario } = useAuth()

  return (
    <SgthTable
      records={data}
      columns={getUsuarioColumns({
        usuarioActualId: usuario?.id,
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
