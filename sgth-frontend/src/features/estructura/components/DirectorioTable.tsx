'use client'

import { SgthTable } from '@/components/ui/SgthTable'
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
    <SgthTable
      records={data}
      fetching={isLoading}
      columns={getDirectorioColumns(handlers)}
      minHeight={150}
    />
  )
}
