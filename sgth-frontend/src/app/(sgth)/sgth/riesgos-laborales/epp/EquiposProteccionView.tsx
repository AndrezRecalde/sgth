'use client'

import { confirmar, PageHeader, PageShell } from '@/components/ui'
import { useState } from 'react'
import { Button, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import { IconPlus, IconEdit, IconTrash, IconClipboardList, IconShieldCheck } from '@tabler/icons-react'
import { DataState, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { useEquiposProteccion, useEquipoProteccionMutations } from '@/features/sso/hooks/useEquiposProteccion'
import { EquipoProteccionModal } from '@/features/sso/components/EquipoProteccionModal'
import { AsignarEppPuestoModal } from '@/features/sso/components/AsignarEppPuestoModal'
import { TIPO_EPP_OPTIONS } from '@/features/sso/schemas/equipoProteccion.schema'
import type { EquipoProteccion } from '@/features/sso/services/tipos'
import type { DataTableColumn } from 'mantine-datatable'

export function EquiposProteccionView() {
  const [page, setPage] = useState(1)
  const [editEquipo, setEditEquipo] = useState<EquipoProteccion | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [asignarOpened, { open: openAsignar, close: closeAsignar }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { eliminar } = useEquipoProteccionMutations()
  const { data, isLoading, error } = useEquiposProteccion({ page })
  const records = data?.data ?? []

  const getTipoLabel = (tipo: string) =>
    TIPO_EPP_OPTIONS.find(o => o.value === tipo)?.label ?? tipo

  const handleEdit = (equipo: EquipoProteccion) => {
    setEditEquipo(equipo)
    open()
  }

  const handleClose = () => {
    setEditEquipo(null)
    close()
  }

  const columns: DataTableColumn<EquipoProteccion>[] = [
    { accessor: 'codigo', title: 'Código', width: 110 },
    {
      accessor: 'nombre',
      title: 'Equipo',
      render: (e) => <Text size="sm" fw={500}>{e.nombre}</Text>,
    },
    {
      accessor: 'tipo',
      title: 'Tipo',
      render: (e) => getTipoLabel(e.tipo),
    },
    { accessor: 'vida_util_meses', title: 'Vida útil (meses)', width: 150 },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 90,
      render: (e) => (
        <StatusBadge tone={e.estado ? 'success' : 'neutral'}>
          {e.estado ? 'Activo' : 'Inactivo'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (equipo) => (
        <TableActions
          actions={[
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
          ]}
        />
      ),
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

      <DataState
        loading={isLoading}
        error={error}
        empty={!records.length}
        emptyProps={{
          icon: IconShieldCheck,
          title: 'Sin equipos de protección',
          description: 'Aún no hay equipos registrados en el catálogo.',
        }}
        page={page}
      >
        <SgthTable
          records={records}
          columns={columns}
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
