'use client'

import { confirmar, DataState, PageHeader, PageShell, SgthTable, Toolbar, type TableAction } from '@/components/ui'
import { useState } from 'react'
import { Button, Select } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import { IconPlus, IconEdit, IconTrash, IconList, IconAlertTriangle } from '@tabler/icons-react'
import { useRiesgosLaborales, useRiesgoLaboralMutations } from '@/features/sso/hooks/useRiesgosLaborales'
import { RiesgoLaboralModal } from '@/features/sso/components/RiesgoLaboralModal'
import { FactoresRiesgoModal } from '@/features/sso/components/FactoresRiesgoModal'
import { columnasRiesgoLaboral } from '@/features/sso/components/riesgoLaboral.columns'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { ESTADO_ACTIVO_OPTIONS, aEstadoActivo } from '@/features/sso/constants/filtros'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { RiesgoLaboral } from '@/features/sso/services/tipos'

export function RiesgosLaboralesView() {
  const compacto = useContainedInput('sm')
  const [page, setPage] = useState(1)
  const [puestoId, setPuestoId] = useState<number | null>(null)
  const [estado, setEstado] = useState<string | null>(null)
  const [editRiesgo, setEditRiesgo] = useState<RiesgoLaboral | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [factoresOpened, { open: openFactores, close: closeFactores }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { eliminar } = useRiesgoLaboralMutations()
  const { data, isLoading, error } = useRiesgosLaborales({
    page,
    puesto_id: puestoId ?? undefined,
    estado: aEstadoActivo(estado),
  })
  const records = data?.data ?? []
  const hayFiltros = Boolean(puestoId || estado)

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
  }

  const limpiar = () => filtrar(() => { setPuestoId(null); setEstado(null) })

  const handleEdit = (riesgo: RiesgoLaboral) => {
    setEditRiesgo(riesgo)
    open()
  }

  const handleClose = () => {
    setEditRiesgo(null)
    close()
  }

  const accionesDe = (riesgo: RiesgoLaboral): TableAction[] => [
    {
      label: 'Editar riesgo',
      icon: <IconEdit size={14} />,
      hidden: !puedeGestionar,
      onClick: () => handleEdit(riesgo),
    },
    {
      label: 'Eliminar riesgo',
      icon: <IconTrash size={14} />,
      color: 'red',
      hidden: !puedeGestionar,
      onClick: () => confirmar({
        title:   'Eliminar riesgo laboral',
        message: 'Se eliminará este riesgo laboral y su valoración. No se puede deshacer.',
        destructiva: true,
        onConfirm: () => eliminar.mutate(riesgo.id),
      }),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Factores de Riesgo Laboral"
        description="Matriz de riesgos por puesto, valorada con la NTP 330"
        // La acción principal de la pantalla va aquí y no flotando sobre la
        // tabla, que es donde estaba (regla 05).
        actions={puedeGestionar ? (
          <>
            <Button
              leftSection={<IconList size={16} />}
              variant="default"
              onClick={openFactores}
            >
              Catálogo de factores
            </Button>
            <Button
              leftSection={<IconPlus size={16} />}
              variant="light"
              onClick={() => { setEditRiesgo(null); open() }}
            >
              Nuevo riesgo
            </Button>
          </>
        ) : undefined}
      />

      {/* El backend filtra la matriz por puesto y por estado desde que existe;
          la pantalla no ofrecía ninguno de los dos, y «qué riesgos tiene este
          puesto» es la pregunta con la que se entra aquí (regla 05). */}
      <Toolbar
        actions={hayFiltros ? (
          <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>
        ) : undefined}
      >
        <BuscarPuestoSelect
          label="Puesto"
          size="sm"
          value={puestoId}
          onChange={(id) => filtrar(() => setPuestoId(id))}
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
        // Un vacío con filtros puestos no es el mismo vacío: ofrecer «aún no se
        // han identificado riesgos» cuando lo que pasa es que el filtro no
        // encuentra nada da por vacío un puesto que quizá sí tiene matriz.
        emptyProps={hayFiltros ? {
          icon: IconAlertTriangle,
          title: 'Ningún riesgo coincide con el filtro',
          description: 'Pruebe con otro puesto o con el otro estado.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
          icon: IconAlertTriangle,
          title: 'Sin riesgos laborales',
          description: 'Aún no se han identificado riesgos en los puestos.',
        }}
        page={page}
      >
        <SgthTable
          records={records}
          columns={columnasRiesgoLaboral(accionesDe)}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
      <RiesgoLaboralModal
        opened={modalOpened}
        onClose={handleClose}
        riesgo={editRiesgo}
      />
      <FactoresRiesgoModal
        opened={factoresOpened}
        onClose={closeFactores}
      />
    </PageShell>
  )
}
