'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Select, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconBan, IconEye, IconPlus, IconTool } from '@tabler/icons-react'
import { DataState, MotivoModal, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar, type TableAction } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { MantenimientoModal } from '@/features/inventario-ti/components/MantenimientoModal'
import { columnasMantenimientos } from '@/features/inventario-ti/components/mantenimientos.columns'
import { useMantenimientoMutations, useMantenimientos } from '@/features/inventario-ti/hooks/useMantenimientos'
import type { FiltrosMantenimientos, Mantenimiento, TipoMantenimiento } from '@/features/inventario-ti/services/tipos'

const TIPOS: { value: TipoMantenimiento; label: string }[] = [
  { value: 'correctivo', label: 'Correctivo' },
  { value: 'preventivo', label: 'Preventivo' },
  { value: 'repotenciacion', label: 'Repotenciación' },
]
const QUIEN = [{ value: 'tic', label: 'TIC' }, { value: 'proveedor', label: 'Proveedor' }]
const MOSTRAR = [{ value: 'no', label: 'Sin los anulados' }, { value: 'si', label: 'Con los anulados' }]

/** Todos los mantenimientos: lo que TIC y los proveedores le han hecho a los equipos. */
export function MantenimientosView() {
  const router = useRouter()
  const compacto = useContainedInput('sm')
  const { hasPermiso } = useAuth()
  const puedeRegistrar = hasPermiso('registrar-mantenimiento')

  const [page, setPage] = useState(1)
  const [filtros, setFiltros] = useState<FiltrosMantenimientos>({})
  const [busqueda, setBusqueda] = useState('')
  const [search] = useDebouncedValue(busqueda.trim(), 300)
  const [registrar, setRegistrar] = useState(false)
  const [anularMant, setAnularMant] = useState<Mantenimiento | null>(null)
  const { anular } = useMantenimientoMutations()

  const { data, isLoading, error } = useMantenimientos({ ...filtros, ...(search.length >= 2 ? { search } : {}) }, page)
  const hayFiltros = busqueda.trim() !== '' || Object.values(filtros).some((v) => v !== undefined)
  const cambiar = (cambio: Partial<FiltrosMantenimientos>) => {
    setFiltros((f) => ({ ...f, ...cambio }))
    setPage(1)
  }
  const limpiar = () => {
    setFiltros({})
    setBusqueda('')
    setPage(1)
  }

  const accionesDe = (m: Mantenimiento): TableAction[] => [
    { label: 'Ver el equipo', icon: <IconEye size={14} />, onClick: () => router.push(ROUTES.TIC.EQUIPO(m.bien_informatico_id)) },
    { label: 'Anular', icon: <IconBan size={14} />, color: 'red', hidden: !puedeRegistrar, disabled: m.anulado, onClick: () => setAnularMant(m) },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Mantenimientos"
        description="Preventivos, reparaciones y repotenciaciones de los equipos, hechos por TIC o por el proveedor"
        actions={puedeRegistrar ? (
          <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setRegistrar(true)}>Registrar mantenimiento</Button>
        ) : undefined}
      />
      <Toolbar actions={hayFiltros ? <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button> : undefined}>
        <TextInput label="Buscar" placeholder="Código, soporte o descripción" {...compacto}
          value={busqueda} onChange={(e) => { setBusqueda(e.currentTarget.value); setPage(1) }} />
        <Select label="Tipo" placeholder="Todos" data={TIPOS} clearable {...compacto}
          value={filtros.tipo_mantenimiento ?? null}
          onChange={(v) => cambiar({ tipo_mantenimiento: TIPOS.find((t) => t.value === v)?.value })} />
        <Select label="Quién" placeholder="TIC y proveedor" data={QUIEN} clearable {...compacto}
          value={filtros.realizado_por ?? null}
          onChange={(v) => cambiar({ realizado_por: v === 'tic' || v === 'proveedor' ? v : undefined })} />
        <Select label="Mostrar" data={MOSTRAR} allowDeselect={false} {...compacto}
          value={filtros.incluir_anulados ? 'si' : 'no'}
          onChange={(v) => cambiar({ incluir_anulados: v === 'si' || undefined })} />
      </Toolbar>
      <DataState
        loading={isLoading}
        error={error}
        empty={!data?.data.length}
        page={page}
        emptyProps={hayFiltros ? {
          icon: IconTool,
          title: 'Ningún mantenimiento coincide',
          description: 'Pruebe con otro término o quite los filtros.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
          icon: IconTool,
          title: 'Todavía no hay mantenimientos',
          description: 'Regístrelos desde aquí o desde la ficha del equipo.',
        }}
      >
        <SgthTable {...PAGINACION_ES} records={data?.data ?? []} columns={columnasMantenimientos(accionesDe)}
          totalRecords={data?.total ?? 0} recordsPerPage={15} page={page} onPageChange={setPage} minHeight={200} />
      </DataState>

      <MantenimientoModal opened={registrar} onClose={() => setRegistrar(false)} />
      <MotivoModal
        opened={anularMant !== null}
        onClose={() => setAnularMant(null)}
        title="Anular mantenimiento"
        descripcion={<>Se anulará el mantenimiento del equipo <b>{anularMant?.bien?.codigo_institucional}</b>. Queda en su historial con el motivo.</>}
        confirmLabel="Anular"
        destructiva
        cargando={anular.isPending}
        onConfirm={(motivo) => anularMant && anular.mutate({ id: anularMant.id, motivo }, { onSuccess: () => setAnularMant(null) })}
      />
    </PageShell>
  )
}
