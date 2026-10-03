'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Select, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconClipboardHeart } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useFemos } from '@/features/dispensario/hooks/useFemo'
import { getFemosColumns } from '@/features/dispensario/components/femos.columns'
import { APTITUD_OPTIONS, TIPO_FICHA_OPTIONS } from '@/features/dispensario/services/femoOptions'
import {
  DataState, PageHeader, PageShell, PAGINACION_ES, SgthTable, Toolbar,
} from '@/components/ui'
import { ROUTES } from '@/config/routes'

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

export function FemoView() {
  const router    = useRouter()
  const contained = useContainedInput('sm')

  const [page, setPage]       = useState(1)
  const [tipo, setTipo]       = useState<string | null>(null)
  const [aptitud, setAptitud] = useState<string | null>(null)
  const [buscar, setBuscar]   = useState('')
  const [buscarDebounced]     = useDebouncedValue(buscar.trim(), 300)

  // Antes no se pasaba la página: el backend devolvía 20 fichas y a partir de
  // la 21 no había manera de llegar a ellas.
  const { data, isLoading, error } = useFemos({
    page,
    per_page:   POR_PAGINA,
    tipo_ficha: tipo ?? undefined,
    aptitud:    aptitud ?? undefined,
    buscar:     buscarDebounced || undefined,
  })
  const fichas = data?.data ?? []

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado filtrado, casi siempre vacía.
  const filtrar = <T,>(set: (v: T) => void) => (v: T) => { set(v); setPage(1) }

  const columns = getFemosColumns({
    onVer: (id) => router.push(ROUTES.SALUD.FEMO_DETALLE(id)),
  })

  const hayFiltros = !!(tipo || aptitud || buscarDebounced)

  return (
    <PageShell>
      <PageHeader
        title="Fichas FEMO"
        description="Evaluaciones médicas ocupacionales del Dispensario"
      />

      <Toolbar>
        <TextInput
          label="Buscar"
          placeholder="Cédula o apellido"
          {...contained}
          value={buscar}
          onChange={(e) => filtrar(setBuscar)(e.currentTarget.value)}
        />
        <Select
          label="Tipo de evaluación"
          placeholder="Todos"
          data={TIPO_FICHA_OPTIONS}
          clearable
          {...contained}
          value={tipo}
          onChange={filtrar(setTipo)}
        />
        <Select
          label="Aptitud"
          placeholder="Todas"
          data={APTITUD_OPTIONS}
          clearable
          {...contained}
          value={aptitud}
          onChange={filtrar(setAptitud)}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!fichas.length}
        emptyProps={{
          icon: IconClipboardHeart,
          title: hayFiltros ? 'Sin coincidencias' : 'Sin fichas registradas',
          description: hayFiltros
            ? 'Ninguna ficha cumple los filtros elegidos.'
            : 'Las fichas nacen al iniciar una solicitud desde la bandeja.',
        }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={fichas}
          columns={columns}
          totalRecords={data?.total ?? fichas.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
    </PageShell>
  )
}
