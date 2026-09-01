'use client'

import { confirmar } from '@/components/ui'
import { useState } from 'react'
import { Button, Group, Badge, Text, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconEdit, IconTrash, IconAlertTriangle } from '@tabler/icons-react'
import { DataState, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import { useAccidentesTrabajo, useAccidenteTrabajoMutations } from '../hooks/useAccidentesTrabajo'
import { AccidenteTrabajoModal } from './AccidenteTrabajoModal'
import { GRAVEDAD_COLORS, TIPO_EVENTO_ACCIDENTE_COLORS, TIPO_EVENTO_ACCIDENTE_OPTIONS } from '../schemas/accidenteTrabajo.schema'
import { formatFecha } from '@/lib/fecha'
import type { AccidenteTrabajo } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

export function AccidentesTrabajoTab() {
  const [page, setPage] = useState(1)
  const [editAccidente, setEditAccidente] = useState<AccidenteTrabajo | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)

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

  const columns: DataTableColumn<AccidenteTrabajo>[] = [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (a) => (
        <Text size="sm" fw={500}>
          {a.servidor ? `${a.servidor.nombre} ${a.servidor.apellido}` : `Servidor ${a.servidor_id}`}
        </Text>
      ),
    },
    {
      accessor: 'tipo_evento',
      title: 'Tipo',
      width: 110,
      render: (a) => (
        <Badge color={TIPO_EVENTO_ACCIDENTE_COLORS[a.tipo_evento] ?? 'gray'} variant="light" size="sm">
          {TIPO_EVENTO_ACCIDENTE_OPTIONS.find(o => o.value === a.tipo_evento)?.label.split(' ')[0] ?? a.tipo_evento}
        </Badge>
      ),
    },
    {
      accessor: 'fecha_accidente',
      title: 'Fecha',
      width: 110,
      render: (a) => formatFecha(a.fecha_accidente),
    },
    { accessor: 'lugar_accidente', title: 'Lugar' },
    {
      accessor: 'gravedad',
      title: 'Gravedad',
      width: 120,
      render: (a) => (
        <Badge color={GRAVEDAD_COLORS[a.gravedad] ?? 'gray'} variant="light" size="sm">
          {a.gravedad}
        </Badge>
      ),
    },
    {
      accessor: 'estado',
      title: 'Investigación',
      width: 130,
      render: (a) => (
        <StatusBadge tone={a.estado ? 'warning' : 'success'}>
          {a.estado ? 'Abierta' : 'Cerrada'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (accidente) => (
        <TableActions
          actions={[
            {
              label: 'Editar accidente',
              icon: <IconEdit size={14} />,
              color: 'blue',
              onClick: () => handleEdit(accidente),
            },
            {
              label: 'Eliminar accidente',
              icon: <IconTrash size={14} />,
              color: 'red',
              onClick: () => confirmar({
                title:   'Eliminar accidente de trabajo',
                message: 'Se eliminará este registro de accidente de trabajo. No se puede deshacer.',
                destructiva: true,
                onConfirm: () => eliminar.mutate(accidente.id),
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
          leftSection={<IconPlus size={16} />}
          color="emerald"
          variant="light"
          onClick={() => { setEditAccidente(null); open() }}
        >
          Nuevo accidente
        </Button>
      </Group>
      <DataState
        loading={isLoading}
        error={error}
        empty={!records.length}
        emptyProps={{
          icon: IconAlertTriangle,
          title: 'Sin accidentes registrados',
          description: 'No se han registrado accidentes ni incidentes de trabajo.',
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
      <AccidenteTrabajoModal
        opened={modalOpened}
        onClose={handleClose}
        accidente={editAccidente}
      />
    </Stack>
  )
}
