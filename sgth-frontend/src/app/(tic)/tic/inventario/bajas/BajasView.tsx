'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Select, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconEye, IconTrashX } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar, type TableAction } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BajaModales, type AccionBaja } from '@/features/inventario-ti/components/BajaModales'
import { accionesBaja, columnasBajas } from '@/features/inventario-ti/components/bajas.columns'
import { ESTADOS_BAJA } from '@/features/inventario-ti/constants/bajas'
import { useBajas, useDocumentoBaja } from '@/features/inventario-ti/hooks/useBajas'
import type { BajaBien, FiltrosBajas } from '@/features/inventario-ti/services/tiposBaja'

/** «Falta entregar» es una pregunta aparte: aprobadas que siguen en TIC. */
const MOSTRAR = [...ESTADOS_BAJA, { value: 'pendientes', label: 'Aprobadas sin entregar a Activos' }]

/**
 * Las bajas de los equipos: las propuestas que esperan al jefe de TIC, las
 * aprobadas (y si ya se entregaron a Activos Fijos) y las rechazadas. Se
 * proponen desde la ficha de cada equipo.
 */
export function BajasView() {
  const router = useRouter()
  const compacto = useContainedInput('sm')
  const { hasPermiso } = useAuth()
  const documento = useDocumentoBaja()

  const [page, setPage] = useState(1)
  const [filtros, setFiltros] = useState<FiltrosBajas>({})
  const [busqueda, setBusqueda] = useState('')
  const [search] = useDebouncedValue(busqueda.trim(), 300)
  const [accion, setAccion] = useState<AccionBaja>(null)

  const { data, isLoading, error } = useBajas({ ...filtros, ...(search.length >= 2 ? { search } : {}) }, page)
  const hayFiltros = busqueda.trim() !== '' || Object.values(filtros).some((v) => v !== undefined)
  const mostrar = filtros.pendientes_de_entrega ? 'pendientes' : (filtros.estado ?? null)
  const cambiarMostrar = (v: string | null) => {
    setFiltros(v === 'pendientes'
      ? { pendientes_de_entrega: true }
      : { estado: ESTADOS_BAJA.find((e) => e.value === v)?.value })
    setPage(1)
  }
  const limpiar = () => {
    setFiltros({})
    setBusqueda('')
    setPage(1)
  }

  const accionesDe = (b: BajaBien): TableAction[] => [
    { label: 'Ver el equipo', icon: <IconEye size={14} />, onClick: () => router.push(ROUTES.TIC.EQUIPO(b.bien_informatico_id)) },
    ...accionesBaja(b, {
      puedeResolver: hasPermiso('dar-baja-bien'),
      abrir: setAccion,
      documento: (x, que) => documento.mutate({ id: x.id, numero: x.numero, que }),
    }),
  ]

  return (
    <PageShell>
      <PageHeader
        title="Bajas"
        description="Los equipos que salen del servicio: el informe técnico, la aprobación del jefe de TIC y la entrega a Activos Fijos"
      />
      <Toolbar actions={hayFiltros ? <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button> : undefined}>
        <TextInput label="Buscar" placeholder="Código o serie del equipo" {...compacto}
          value={busqueda} onChange={(e) => { setBusqueda(e.currentTarget.value); setPage(1) }} />
        <Select label="Mostrar" placeholder="Todas" data={MOSTRAR} clearable {...compacto}
          value={mostrar} onChange={cambiarMostrar} />
      </Toolbar>
      <DataState
        loading={isLoading}
        error={error}
        empty={!data?.data.length}
        page={page}
        emptyProps={hayFiltros ? {
          icon: IconTrashX,
          title: 'Ninguna baja coincide',
          description: 'Pruebe con otro término o quite los filtros.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
          icon: IconTrashX,
          title: 'Todavía no hay bajas',
          description: 'Se proponen desde la ficha del equipo, con su informe técnico.',
        }}
      >
        <SgthTable {...PAGINACION_ES} records={data?.data ?? []} columns={columnasBajas(accionesDe)}
          totalRecords={data?.total ?? 0} recordsPerPage={15} page={page} onPageChange={setPage} minHeight={200} />
      </DataState>

      <BajaModales accion={accion} onClose={() => setAccion(null)} />
    </PageShell>
  )
}
