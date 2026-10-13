'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconDevices, IconEdit, IconEye, IconPlus, IconTrash } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, confirmar, type TableAction } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { BienesFiltros } from '@/features/inventario-ti/components/BienesFiltros'
import { columnasBienes } from '@/features/inventario-ti/components/bienes.columns'
import { useBienes, useRetirarBien } from '@/features/inventario-ti/hooks/useBienes'
import type { BienInformatico, FiltrosBienes } from '@/features/inventario-ti/services/tipos'

const RETARDO_BUSQUEDA_MS = 300

export function EquiposView() {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  // El auditor consulta con `ver-inventario-ti`; registrar y corregir es de TIC.
  const puedeGestionar = hasPermiso('gestionar-inventario-ti')

  const [page, setPage] = useState(1)
  const [filtros, setFiltros] = useState<FiltrosBienes>({})
  const [busqueda, setBusqueda] = useState('')
  const [search] = useDebouncedValue(busqueda.trim(), RETARDO_BUSQUEDA_MS)

  const consulta = { ...filtros, ...(search.length >= 2 ? { search } : {}) }
  const { data, isLoading, error } = useBienes(consulta, page)
  const retirar = useRetirarBien()

  const hayFiltros = busqueda.trim() !== '' || Object.values(filtros).some((v) => v !== undefined)

  // Cambiar un filtro vuelve a la primera página: la actual del resultado
  // filtrado casi siempre está vacía.
  const cambiar = (cambio: Partial<FiltrosBienes>) => {
    setFiltros((f) => ({ ...f, ...cambio }))
    setPage(1)
  }
  const buscar = (texto: string) => {
    setBusqueda(texto)
    setPage(1)
  }
  const limpiar = () => {
    setFiltros({})
    setBusqueda('')
    setPage(1)
  }

  const accionesDe = (b: BienInformatico): TableAction[] => [
    { label: 'Ver ficha', icon: <IconEye size={14} />, onClick: () => router.push(ROUTES.TIC.EQUIPO(b.id)) },
    { label: 'Editar', icon: <IconEdit size={14} />, hidden: !puedeGestionar, onClick: () => router.push(ROUTES.TIC.EQUIPO_EDITAR(b.id)) },
    {
      label: 'Retirar ficha',
      icon: <IconTrash size={14} />,
      color: 'red',
      hidden: !puedeGestionar,
      onClick: () => confirmar({
        title: 'Retirar la ficha del equipo',
        message: <>Se borrará la ficha de <b>{b.codigo_institucional}</b>, registrada por error. Si el equipo salió de servicio, corresponde una baja.</>,
        destructiva: true,
        onConfirm: () => retirar.mutate(b.id),
      }),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Equipos"
        description="Inventario de equipos informáticos: quién los tiene, dónde están y en qué estado"
        actions={puedeGestionar ? (
          <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => router.push(ROUTES.TIC.EQUIPO_NUEVO)}>
            Registrar equipo
          </Button>
        ) : undefined}
      />

      <BienesFiltros
        filtros={filtros}
        busqueda={busqueda}
        onBusqueda={buscar}
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
          icon: IconDevices,
          title: 'Ningún equipo coincide con los filtros',
          description: 'Pruebe con otro término o quite alguno de los filtros.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
          icon: IconDevices,
          title: 'Todavía no hay equipos en el inventario',
          description: 'Registre el primero, o espere a la carga del Excel de TIC.',
          action: puedeGestionar
            ? <Button variant="light" onClick={() => router.push(ROUTES.TIC.EQUIPO_NUEVO)}>Registrar equipo</Button>
            : undefined,
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={data?.data ?? []}
          columns={columnasBienes(accionesDe)}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          onRowClick={({ record }) => router.push(ROUTES.TIC.EQUIPO(record.id))}
          minHeight={200}
        />
      </DataState>
    </PageShell>
  )
}
