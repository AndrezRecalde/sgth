'use client'

import { PAGINACION_ES, PageHeader, PageShell, Toolbar, confirmar, type TableAction } from '@/components/ui'
import { useState } from 'react'
import { Button, Select } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import { IconPlus, IconEdit, IconTrash, IconClipboardList, IconShieldCheck } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { useEquiposProteccion, useEquipoProteccionMutations } from '@/features/sso/hooks/useEquiposProteccion'
import { EquipoProteccionModal } from '@/features/sso/components/EquipoProteccionModal'
import { AsignarEppPuestoModal } from '@/features/sso/components/AsignarEppPuestoModal'
import { columnasEquipoProteccion } from '@/features/sso/components/equipoProteccion.columns'
import { TIPO_EPP_OPTIONS } from '@/features/sso/schemas/equipoProteccion.schema'
import { ESTADO_ACTIVO_OPTIONS, aEstadoActivo } from '@/features/sso/constants/filtros'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { EquipoProteccion } from '@/features/sso/services/tipos'

export function EquiposProteccionView() {
  const compacto = useContainedInput('sm')
  const [page, setPage] = useState(1)
  const [tipo, setTipo] = useState<string | null>(null)
  const [estado, setEstado] = useState<string | null>(null)
  const [editEquipo, setEditEquipo] = useState<EquipoProteccion | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [asignarOpened, { open: openAsignar, close: closeAsignar }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { eliminar } = useEquipoProteccionMutations()
  const { data, isLoading, error } = useEquiposProteccion({
    page,
    tipo: tipo ?? undefined,
    estado: aEstadoActivo(estado),
  })
  const records = data?.data ?? []
  const hayFiltros = Boolean(tipo || estado)

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
  }

  const limpiar = () => filtrar(() => { setTipo(null); setEstado(null) })

  const handleEdit = (equipo: EquipoProteccion) => {
    setEditEquipo(equipo)
    open()
  }

  const handleClose = () => {
    setEditEquipo(null)
    close()
  }

  const accionesDe = (equipo: EquipoProteccion): TableAction[] => [
    {
      label: 'Editar equipo',
      icon: <IconEdit size={14} />,
      hidden: !puedeGestionar,
      onClick: () => handleEdit(equipo),
    },
    {
      label: 'Eliminar equipo',
      icon: <IconTrash size={14} />,
      color: 'red',
      hidden: !puedeGestionar,
      onClick: () => confirmar({
        title:   'Eliminar equipo',
        message: <>Se eliminará el equipo <b>{equipo.nombre}</b>. No se puede deshacer.</>,
        destructiva: true,
        onConfirm: () => eliminar.mutate(equipo.id),
      }),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Equipos de Protección Personal"
        description="Catálogo de equipos y el EPP que requiere cada puesto"
        // La acción principal de la pantalla va aquí y no flotando sobre la
        // tabla, que es donde estaba (regla 05).
        actions={puedeGestionar ? (
          <>
            <Button
              leftSection={<IconClipboardList size={16} />}
              variant="default"
              onClick={openAsignar}
            >
              EPP por puesto
            </Button>
            <Button
              leftSection={<IconPlus size={16} />}
              variant="light"
              onClick={() => { setEditEquipo(null); open() }}
            >
              Nuevo equipo
            </Button>
          </>
        ) : undefined}
      />

      {/* El backend filtra este listado por tipo y por estado desde que existe;
          la pantalla nunca ofreció ninguno de los dos, así que con el catálogo
          crecido había que recorrer las páginas a ojo (regla 05). */}
      <Toolbar
        actions={hayFiltros ? (
          <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>
        ) : undefined}
      >
        <Select
          label="Tipo de equipo"
          placeholder="Todos"
          data={TIPO_EPP_OPTIONS}
          clearable
          style={{ minWidth: 220 }}
          {...compacto}
          value={tipo}
          onChange={(v) => filtrar(() => setTipo(v))}
        />
        <Select
          label="Estado"
          placeholder="Todos"
          data={ESTADO_ACTIVO_OPTIONS}
          clearable
          style={{ minWidth: 160 }}
          {...compacto}
          value={estado}
          onChange={(v) => filtrar(() => setEstado(v))}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!records.length}
        // Un vacío con filtros puestos no es el mismo vacío: ofrecer «registre
        // el primero» cuando lo que pasa es que el filtro no encuentra nada
        // manda a dar de alta un equipo que quizá ya está en el catálogo.
        emptyProps={hayFiltros ? {
          icon: IconShieldCheck,
          title: 'Ningún equipo coincide con el filtro',
          description: 'Pruebe con otro tipo de equipo o con el otro estado.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
          icon: IconShieldCheck,
          title: 'Sin equipos de protección',
          description: 'Aún no hay equipos registrados en el catálogo.',
        }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={records}
          columns={columnasEquipoProteccion(accionesDe)}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
      <EquipoProteccionModal
        opened={modalOpened}
        onClose={handleClose}
        equipo={editEquipo}
      />
      <AsignarEppPuestoModal
        opened={asignarOpened}
        onClose={closeAsignar}
      />
    </PageShell>
  )
}
