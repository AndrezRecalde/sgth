'use client'

import { confirmar } from '@/components/ui'
import { useState } from 'react'
import { Button, Group, Text, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconEdit, IconTrash, IconClipboardList, IconShieldCheck } from '@tabler/icons-react'
import { DataState, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { useEquiposProteccion, useEquipoProteccionMutations } from '../hooks/useEquiposProteccion'
import { EquipoProteccionModal } from './EquipoProteccionModal'
import { AsignarEppPuestoModal } from './AsignarEppPuestoModal'
import { TIPO_EPP_OPTIONS } from '../schemas/equipoProteccion.schema'
import type { EquipoProteccion } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

export function EquiposProteccionTab() {
  const [page, setPage] = useState(1)
  const [editEquipo, setEditEquipo] = useState<EquipoProteccion | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [asignarOpened, { open: openAsignar, close: closeAsignar }] = useDisclosure(false)

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
              color: 'blue',
              onClick: () => handleEdit(equipo),
            },
            {
              label: 'Eliminar equipo',
              icon: <IconTrash size={14} />,
              color: 'red',
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
    <Stack gap="md">
      <Group justify="flex-end" mb="md">
        <Button
          leftSection={<IconClipboardList size={16} />}
          variant="default"
          onClick={openAsignar}
        >
          EPP por puesto
        </Button>
        <Button
          leftSection={<IconPlus size={16} />}
          color="emerald"
          variant="light"
          onClick={() => { setEditEquipo(null); open() }}
        >
          Nuevo equipo
        </Button>
      </Group>
      <DataState
        loading={isLoading}
        error={error}
        empty={!records.length}
        emptyProps={{
          icon: IconShieldCheck,
          title: 'Sin equipos de protección',
          description: 'Aún no hay equipos registrados en el catálogo.',
        }}
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
    </Stack>
  )
}
