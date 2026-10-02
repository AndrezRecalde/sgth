'use client'

import { confirmar, DataState, PageHeader, PageShell, SgthTable, type TableAction } from '@/components/ui'
import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import { IconPlus, IconEdit, IconTrash, IconAlertTriangle } from '@tabler/icons-react'
import { useAccidentesTrabajo, useAccidenteTrabajoMutations } from '@/features/sso/hooks/useAccidentesTrabajo'
import { AccidenteTrabajoModal } from '@/features/sso/components/AccidenteTrabajoModal'
import { columnasAccidenteTrabajo } from '@/features/sso/components/accidenteTrabajo.columns'
import type { AccidenteTrabajo } from '@/features/sso/services/tipos'

export function AccidentesTrabajoView() {
  const [page, setPage] = useState(1)
  const [editAccidente, setEditAccidente] = useState<AccidenteTrabajo | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { eliminar } = useAccidenteTrabajoMutations()
  const { data, isLoading, error } = useAccidentesTrabajo({ page })
  const records = data?.data ?? []

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

      <DataState
        loading={isLoading}
        error={error}
        empty={!records.length}
        emptyProps={{
          icon: IconAlertTriangle,
          title: 'Sin accidentes registrados',
          description: 'No se han registrado accidentes ni incidentes de trabajo.',
        }}
        page={page}
      >
        <SgthTable
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
