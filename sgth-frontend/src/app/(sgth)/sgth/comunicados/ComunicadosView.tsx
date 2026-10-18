'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconNews, IconPlus } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { confirmar, DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { ComunicadosFiltros } from '@/features/comunicados/components/ComunicadosFiltros'
import { columnasComunicados } from '@/features/comunicados/components/comunicados.columns'
import { POR_PAGINA_COMUNICADOS, useComunicados } from '@/features/comunicados/hooks/useComunicados'
import { useMutacionesComunicado } from '@/features/comunicados/hooks/useMutacionesComunicado'
import { usePermisosComunicados } from '@/features/comunicados/hooks/usePermisosComunicados'
import type { FiltrosComunicados } from '@/features/comunicados/services/tipos'

const RETARDO_BUSQUEDA_MS = 300

/**
 * Las noticias y comunicados de Talento Humano (docs/diseno-noticias-
 * comunicados.md, 6.2): lo publicado, lo programado y los borradores, con a
 * quién va y cuántos lo leyeron.
 */
export function ComunicadosView() {
  const router = useRouter()
  const { redacta } = usePermisosComunicados()
  const { duplicar, eliminar } = useMutacionesComunicado()

  const [page, setPage] = useState(1)
  const [filtros, setFiltros] = useState<FiltrosComunicados>({})
  const [busqueda, setBusqueda] = useState('')
  const [buscar] = useDebouncedValue(busqueda.trim(), RETARDO_BUSQUEDA_MS)

  const consulta = { ...filtros, ...(buscar.length >= 2 ? { buscar } : {}) }
  const { data, isLoading, error, refetch } = useComunicados(consulta, page)
  const comunicados = data?.data ?? []

  const hayFiltros = busqueda.trim() !== '' || Object.values(filtros).some((v) => v !== undefined)
  const cambiar = (cambio: Partial<FiltrosComunicados>) => {
    setFiltros((f) => ({ ...f, ...cambio }))
    setPage(1)
  }
  const limpiar = () => {
    setFiltros({})
    setBusqueda('')
    setPage(1)
  }

  const columnas = columnasComunicados({
    redacta,
    onVer: (c) => router.push(ROUTES.SGTH.COMUNICADO(c.id)),
    onEditar: (c) => router.push(ROUTES.SGTH.COMUNICADO_EDITAR(c.id)),
    onDuplicar: (c) => duplicar.mutate(c.id, {
      onSuccess: (copia) => router.push(ROUTES.SGTH.COMUNICADO_EDITAR(copia.id)),
    }),
    onEliminar: (c) => confirmar({
      title: 'Eliminar borrador',
      message: <>Se eliminará <b>{c.titulo}</b> con sus documentos. No se puede deshacer.</>,
      confirmLabel: 'Eliminar',
      destructiva: true,
      onConfirm: () => eliminar.mutate(c.id),
    }),
  })

  const nuevo = (
    <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => router.push(ROUTES.SGTH.COMUNICADO_NUEVO)}>
      Nueva publicación
    </Button>
  )

  return (
    <PageShell>
      <PageHeader
        title="Noticias y comunicados"
        description="Lo que Talento Humano publica en el Portal del servidor"
        actions={redacta && nuevo}
      />

      <ComunicadosFiltros
        filtros={filtros}
        busqueda={busqueda}
        onBusqueda={(texto) => { setBusqueda(texto); setPage(1) }}
        onCambiar={cambiar}
        onLimpiar={limpiar}
        hayFiltros={hayFiltros}
      />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las publicaciones"
        onRetry={refetch}
        empty={!comunicados.length}
        emptyProps={hayFiltros
          ? { icon: IconNews, title: 'Nada con estos filtros', description: 'Cambie o quite los filtros para ver las demás publicaciones.' }
          : {
              icon: IconNews,
              title: 'Todavía no hay publicaciones',
              description: 'Redacte la primera noticia o comunicado: quedará como borrador hasta que se publique.',
              action: redacta ? nuevo : undefined,
            }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={comunicados}
          columns={columnas}
          totalRecords={data?.total ?? 0}
          recordsPerPage={POR_PAGINA_COMUNICADOS}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
    </PageShell>
  )
}
