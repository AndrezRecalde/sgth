'use client'

import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar, confirmar, type TableAction } from '@/components/ui'
import { useState } from 'react'
import { Button, Select } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import { IconPlus, IconEdit, IconTrash, IconAlertTriangle } from '@tabler/icons-react'
import { useAccidentesTrabajo, useAccidenteTrabajoMutations } from '@/features/sso/hooks/useAccidentesTrabajo'
import { AccidenteTrabajoModal } from '@/features/sso/components/AccidenteTrabajoModal'
import { columnasAccidenteTrabajo } from '@/features/sso/components/accidenteTrabajo.columns'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { ESTADO_INVESTIGACION_OPTIONS, aEstadoActivo } from '@/features/sso/constants/filtros'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { AccidenteTrabajo } from '@/features/sso/services/tipos'

export function AccidentesTrabajoView() {
  const compacto = useContainedInput('sm')
  const [page, setPage] = useState(1)
  const [servidorId, setServidorId] = useState<number | null>(null)
  const [estado, setEstado] = useState<string | null>(null)
  const [editAccidente, setEditAccidente] = useState<AccidenteTrabajo | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { eliminar } = useAccidenteTrabajoMutations()
  const { data, isLoading, error } = useAccidentesTrabajo({
    page,
    servidor_id: servidorId ?? undefined,
    estado: aEstadoActivo(estado),
  })
  const records = data?.data ?? []
  const hayFiltros = Boolean(servidorId || estado)

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
  }

  const limpiar = () => filtrar(() => { setServidorId(null); setEstado(null) })

  const handleEdit = (accidente: AccidenteTrabajo) => {
    setEditAccidente(accidente)
    open()
  }

  const handleClose = () => {
    setEditAccidente(null)
    close()
  }

  const accionesDe = (accidente: AccidenteTrabajo): TableAction[] => [
    {
      label: 'Editar accidente',
      icon: <IconEdit size={14} />,
      hidden: !puedeGestionar,
      onClick: () => handleEdit(accidente),
    },
    {
      label: 'Eliminar accidente',
      icon: <IconTrash size={14} />,
      color: 'red',
      hidden: !puedeGestionar,
      onClick: () => confirmar({
        title:   'Eliminar accidente de trabajo',
        message: 'Se eliminará este registro de accidente de trabajo. No se puede deshacer.',
        destructiva: true,
        onConfirm: () => eliminar.mutate(accidente.id),
      }),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Accidentes de Trabajo"
        description="Registro e investigación de accidentes e incidentes laborales"
        // La acción principal de la pantalla va aquí y no flotando sobre la
        // tabla, que es donde estaba (regla 05).
        actions={puedeGestionar ? (
          <>
            <Button
              leftSection={<IconPlus size={16} />}
              variant="light"
              onClick={() => { setEditAccidente(null); open() }}
            >
              Nuevo accidente
            </Button>
          </>
        ) : undefined}
      />

      {/* El backend filtra por servidor y por estado de la investigación desde
          que existe, y la pantalla no ofrecía ninguno de los dos: «qué
          investigaciones siguen abiertas» es lo que hay que poder preguntar
          aquí, y era lo único que no se podía (regla 05). */}
      <Toolbar
        actions={hayFiltros ? (
          <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>
        ) : undefined}
      >
        <BuscarServidorSelect
          label="Servidor"
          size="sm"
          value={servidorId}
          onChange={(id) => filtrar(() => setServidorId(id))}
        />
        <Select
          label="Investigación"
          placeholder="Todas"
          data={ESTADO_INVESTIGACION_OPTIONS}
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
        empty={!records.length}
        // Un vacío con filtros puestos no es el mismo vacío: decir que no hay
        // accidentes registrados cuando lo que pasa es que el filtro no
        // encuentra nada afirma de un servidor algo que no se ha consultado.
        emptyProps={hayFiltros ? {
          icon: IconAlertTriangle,
          title: 'Ningún accidente coincide con el filtro',
          description: 'Pruebe con otro servidor o con el otro estado de investigación.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
          icon: IconAlertTriangle,
          title: 'Sin accidentes registrados',
          description: 'No se han registrado accidentes ni incidentes de trabajo.',
        }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={records}
          columns={columnasAccidenteTrabajo(accionesDe)}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
      <AccidenteTrabajoModal
        opened={modalOpened}
        onClose={handleClose}
        accidente={editAccidente}
      />
    </PageShell>
  )
}
