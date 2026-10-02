'use client'

import { useState } from 'react'
import { Button, Select } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconEdit, IconTrash, IconSchool } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar, confirmar, type TableAction } from '@/components/ui'
import { useCapacitaciones, useCapacitacionMutations } from '@/features/sso/hooks/useCapacitaciones'
import { CapacitacionSsoModal } from '@/features/sso/components/CapacitacionSsoModal'
import { columnasCapacitacion } from '@/features/sso/components/capacitacion.columns'
import { ESTADO_ACTIVO_OPTIONS, aEstadoActivo } from '@/features/sso/constants/filtros'
import type { CapacitacionSso } from '@/features/sso/services/tipos'

export function CapacitacionesSsoView() {
  const compacto = useContainedInput('sm')
  const [page, setPage] = useState(1)
  const [estado, setEstado] = useState<string | null>(null)
  const [editando, setEditando] = useState<CapacitacionSso | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { eliminar } = useCapacitacionMutations()
  const { data, isLoading, error, refetch } = useCapacitaciones({
    page,
    estado: aEstadoActivo(estado),
  })
  const records = data?.data ?? []
  const hayFiltros = Boolean(estado)

  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
  }

  const limpiar = () => filtrar(() => setEstado(null))

  const abrirNueva = () => { setEditando(null); open() }
  const abrirEdicion = (capacitacion: CapacitacionSso) => { setEditando(capacitacion); open() }
  const cerrar = () => { setEditando(null); close() }

  const accionesDe = (capacitacion: CapacitacionSso): TableAction[] => [
    {
      label: 'Editar capacitación',
      icon: <IconEdit size={14} />,
      hidden: !puedeGestionar,
      onClick: () => abrirEdicion(capacitacion),
    },
    {
      label: 'Eliminar capacitación',
      icon: <IconTrash size={14} />,
      color: 'red',
      hidden: !puedeGestionar,
      onClick: () => confirmar({
        title:   'Eliminar capacitación',
        message: 'Se eliminará esta capacitación y sus horas dejarán de contar en los índices proactivos del período. No se puede deshacer.',
        destructiva: true,
        onConfirm: () => eliminar.mutate(capacitacion.id),
      }),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Capacitaciones en SSO"
        description="Capacitaciones del período, de donde salen las horas del índice proactivo"
        actions={puedeGestionar ? (
          <Button leftSection={<IconPlus size={16} />} variant="light" onClick={abrirNueva}>
            Nueva capacitación
          </Button>
        ) : undefined}
      />

      <Toolbar
        actions={hayFiltros ? (
          <Button variant="subtle" onClick={limpiar}>Quitar el filtro</Button>
        ) : undefined}
      >
        <Select
          label="Estado"
          placeholder="Todas"
          data={ESTADO_ACTIVO_OPTIONS}
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
        errorTitle="No se pudieron cargar las capacitaciones"
        errorHint="No quiere decir que no haya capacitaciones registradas: no se pudieron consultar."
        onRetry={() => refetch()}
        empty={!records.length}
        emptyProps={hayFiltros ? {
          icon: IconSchool,
          title: 'Ninguna capacitación coincide con el filtro',
          description: 'Pruebe con el otro estado.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar el filtro</Button>,
        } : {
          icon: IconSchool,
          title: 'Sin capacitaciones registradas',
          description: 'De aquí salen dos de los cuatro índices proactivos: las capacitaciones del período y su total de horas.',
          action: puedeGestionar ? (
            <Button variant="light" leftSection={<IconPlus size={16} />} onClick={abrirNueva}>
              Nueva capacitación
            </Button>
          ) : undefined,
        }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={records}
          columns={columnasCapacitacion(accionesDe)}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <CapacitacionSsoModal opened={modalOpened} onClose={cerrar} capacitacion={editando} />
    </PageShell>
  )
}
