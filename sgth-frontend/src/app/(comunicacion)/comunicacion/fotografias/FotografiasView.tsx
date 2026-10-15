'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconCamera, IconHistory, IconPhotoOff, IconPhotoUp, IconUpload } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, confirmar, type TableAction } from '@/components/ui'
import { CargaMasivaModal } from '@/features/comunicacion/components/CargaMasivaModal'
import { FotografiasFiltros } from '@/features/comunicacion/components/FotografiasFiltros'
import { HistorialFotografiasDrawer } from '@/features/comunicacion/components/HistorialFotografiasDrawer'
import { SubirFotografiaModal } from '@/features/comunicacion/components/SubirFotografiaModal'
import { columnasFotografias } from '@/features/comunicacion/components/fotografias.columns'
import { useFotografias, useMutacionesFotografia } from '@/features/comunicacion/hooks/useFotografias'
import type { FiltrosFotografias, ServidorConFotografia } from '@/features/comunicacion/services/tipos'

const RETARDO_BUSQUEDA_MS = 300

/**
 * Fotografías de los servidores (docs/diseno-comunicacion-social.md, 5.1;
 * fase 1.2): quién tiene foto, subirla de una en una con su recorte, el
 * historial y la carga masiva por cédula.
 */
export function FotografiasView() {
  const [page, setPage] = useState(1)
  const [filtros, setFiltros] = useState<FiltrosFotografias>({})
  const [busqueda, setBusqueda] = useState('')
  const [buscar] = useDebouncedValue(busqueda.trim(), RETARDO_BUSQUEDA_MS)

  const [subiendo, setSubiendo] = useState<ServidorConFotografia | null>(null)
  const [historial, setHistorial] = useState<ServidorConFotografia | null>(null)
  const [cargaMasiva, setCargaMasiva] = useState(false)

  const consulta = { ...filtros, ...(buscar.length >= 2 ? { buscar } : {}) }
  const { data, isLoading, error } = useFotografias(consulta, page)
  const { retirar } = useMutacionesFotografia()

  const hayFiltros = busqueda.trim() !== '' || Object.values(filtros).some((v) => v !== undefined)

  // Cambiar un filtro vuelve a la primera página.
  const cambiar = (cambio: Partial<FiltrosFotografias>) => {
    setFiltros((f) => ({ ...f, ...cambio }))
    setPage(1)
  }
  const limpiar = () => {
    setFiltros({})
    setBusqueda('')
    setPage(1)
  }

  const accionesDe = (s: ServidorConFotografia): TableAction[] => [
    {
      label: s.fotografia ? 'Reemplazar la foto' : 'Subir la foto',
      icon: <IconPhotoUp size={14} />,
      onClick: () => setSubiendo(s),
    },
    { label: 'Historial de fotos', icon: <IconHistory size={14} />, onClick: () => setHistorial(s) },
    {
      label: 'Retirar la foto',
      icon: <IconPhotoOff size={14} />,
      color: 'red',
      hidden: !s.fotografia,
      onClick: () => confirmar({
        title: 'Retirar la foto vigente',
        message: <>{s.nombre_completo} quedará <b>sin foto</b>. El archivo sigue en el historial y se puede restaurar.</>,
        confirmLabel: 'Retirar la foto',
        destructiva: true,
        onConfirm: () => retirar.mutate(s.id),
      }),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Fotografías"
        description="La foto de cada servidor, para su credencial y su expediente"
        actions={(
          <Button variant="light" leftSection={<IconUpload size={16} />} onClick={() => setCargaMasiva(true)}>
            Carga masiva
          </Button>
        )}
      />

      <FotografiasFiltros
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
        empty={!data?.data.length}
        page={page}
        emptyProps={hayFiltros ? {
          icon: IconCamera,
          title: 'Ningún servidor coincide con los filtros',
          description: 'Pruebe con otro nombre o cédula, o quite alguno de los filtros.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
          icon: IconCamera,
          title: 'No hay servidores activos',
          description: 'Cuando Talento Humano registre a los servidores, aparecerán aquí para tomarles la foto.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={data?.data ?? []}
          columns={columnasFotografias(accionesDe)}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <SubirFotografiaModal servidor={subiendo} onClose={() => setSubiendo(null)} />
      <HistorialFotografiasDrawer servidor={historial} onClose={() => setHistorial(null)} />
      <CargaMasivaModal opened={cargaMasiva} onClose={() => setCargaMasiva(false)} />
    </PageShell>
  )
}
