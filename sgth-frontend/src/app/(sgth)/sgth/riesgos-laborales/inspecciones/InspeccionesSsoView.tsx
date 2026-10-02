'use client'

import { useState } from 'react'
import { Button, Select } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconEdit, IconTrash, IconClipboardList } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  confirmar, DataState, PageHeader, PageShell, SgthTable, Toolbar, type TableAction,
} from '@/components/ui'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useInspecciones, useInspeccionMutations } from '@/features/sso/hooks/useInspecciones'
import { InspeccionSsoModal } from '@/features/sso/components/InspeccionSsoModal'
import { columnasInspeccion } from '@/features/sso/components/inspeccion.columns'
import { ESTADO_SEGUIMIENTO_OPTIONS, aEstadoActivo } from '@/features/sso/constants/filtros'
import type { InspeccionSso } from '@/features/sso/services/tipos'

export function InspeccionesSsoView() {
  const compacto = useContainedInput('sm')
  const [page, setPage] = useState(1)
  const [unidad, setUnidad] = useState<string | null>(null)
  const [estado, setEstado] = useState<string | null>(null)
  const [editando, setEditando] = useState<InspeccionSso | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  const { eliminar } = useInspeccionMutations()
  const { data, isLoading, error, refetch } = useInspecciones({
    page,
    unidad_administrativa_id: unidad ? Number(unidad) : undefined,
    estado: aEstadoActivo(estado),
  })
  const records = data?.data ?? []
  const hayFiltros = Boolean(unidad || estado)

  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
  }

  const limpiar = () => filtrar(() => { setUnidad(null); setEstado(null) })

  const abrirNueva = () => { setEditando(null); open() }
  const abrirEdicion = (inspeccion: InspeccionSso) => { setEditando(inspeccion); open() }
  const cerrar = () => { setEditando(null); close() }

  const accionesDe = (inspeccion: InspeccionSso): TableAction[] => [
    {
      label: 'Editar inspección',
      icon: <IconEdit size={14} />,
      hidden: !puedeGestionar,
      onClick: () => abrirEdicion(inspeccion),
    },
    {
      label: 'Eliminar inspección',
      icon: <IconTrash size={14} />,
      color: 'red',
      hidden: !puedeGestionar,
      onClick: () => confirmar({
        title:   'Eliminar inspección',
        message: 'Se eliminará esta inspección y dejará de contar en los índices proactivos del período. No se puede deshacer.',
        destructiva: true,
        onConfirm: () => eliminar.mutate(inspeccion.id),
      }),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Inspecciones de Seguridad"
        description="Inspecciones por unidad administrativa, con sus hallazgos y recomendaciones"
        actions={puedeGestionar ? (
          <Button leftSection={<IconPlus size={16} />} variant="light" onClick={abrirNueva}>
            Nueva inspección
          </Button>
        ) : undefined}
      />

      <Toolbar
        actions={hayFiltros ? (
          <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>
        ) : undefined}
      >
        <Select
          label="Unidad administrativa"
          placeholder="Todas"
          data={unidadOptions}
          searchable
          clearable
          style={{ minWidth: 240 }}
          {...compacto}
          value={unidad}
          onChange={(v) => filtrar(() => setUnidad(v))}
        />
        <Select
          label="Seguimiento"
          placeholder="Todos"
          data={ESTADO_SEGUIMIENTO_OPTIONS}
          clearable
          style={{ minWidth: 170 }}
          {...compacto}
          value={estado}
          onChange={(v) => filtrar(() => setEstado(v))}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las inspecciones"
        errorHint="No quiere decir que no haya inspecciones registradas: no se pudieron consultar."
        onRetry={() => refetch()}
        empty={!records.length}
        emptyProps={hayFiltros ? {
          icon: IconClipboardList,
          title: 'Ninguna inspección coincide con el filtro',
          description: 'Pruebe con otra unidad o con el otro estado de seguimiento.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
          icon: IconClipboardList,
          title: 'Sin inspecciones registradas',
          description: 'Las inspecciones del período alimentan uno de los cuatro índices proactivos. Registre la primera.',
          action: puedeGestionar ? (
            <Button variant="light" leftSection={<IconPlus size={16} />} onClick={abrirNueva}>
              Nueva inspección
            </Button>
          ) : undefined,
        }}
        page={page}
      >
        <SgthTable
          records={records}
          columns={columnasInspeccion(accionesDe)}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <InspeccionSsoModal opened={modalOpened} onClose={cerrar} inspeccion={editando} />
    </PageShell>
  )
}
