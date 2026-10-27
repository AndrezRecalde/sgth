'use client'

import { useState } from 'react'
import { useDebouncedValue } from '@mantine/hooks'
import { PageHeader, PageShell } from '@/components/ui'
import { DirectorioTable } from '@/features/estructura/components/DirectorioTable'
import { DirectorioToolbar } from '@/features/estructura/components/DirectorioToolbar'
import { useDirectorio } from '@/features/estructura/hooks/useDirectorio'

// Como en la pantalla de TIC: cada tecla pedía el directorio entero.
const RETARDO_BUSQUEDA_MS = 300

/**
 * El directorio telefónico, en consulta para todo el personal. Las
 * extensiones las mantiene TIC (docs/plan-roles-y-accesos.md, PR 11).
 */
export function DirectorioView() {
  const [search, setSearch] = useState('')
  const [unidadId, setUnidadId] = useState<string | null>(null)

  const [searchConRetardo] = useDebouncedValue(search, RETARDO_BUSQUEDA_MS)

  const { data: directorio = [], isLoading } = useDirectorio({
    search: searchConRetardo || undefined,
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
  })

  return (
    <PageShell>
      <PageHeader
        title="Directorio telefónico"
        description="Las extensiones de cada unidad. Si una no está o ha cambiado, comuníquelo a la Unidad de TIC."
      />

      <DirectorioToolbar
        onSearch={setSearch}
        onUnidadChange={setUnidadId}
        onClear={() => {
          setSearch('')
          setUnidadId(null)
        }}
      />

      <DirectorioTable data={directorio} isLoading={isLoading} />
    </PageShell>
  )
}
