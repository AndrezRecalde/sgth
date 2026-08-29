'use client'

import { useState } from 'react'
import { Box, Button, Group, Badge, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconEdit, IconTrash } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
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
  const { data, isLoading } = useAccidentesTrabajo({ page })
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
        <Badge color={a.estado ? 'orange' : 'emerald'} variant="light" size="sm">
          {a.estado ? 'Abierta' : 'Cerrada'}
        </Badge>
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
              onClick: () => {
                if (confirm('¿Eliminar este registro de accidente de trabajo?')) {
                  eliminar.mutate(accidente.id)
                }
              },
            },
          ]}
        />
      ),
    },
  ]

  return (
    <Box>
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
      <SgthTable
        records={records}
        columns={columns}
        fetching={isLoading}
        totalRecords={data?.total ?? 0}
        recordsPerPage={15}
        page={page}
        onPageChange={setPage}
        minHeight={200}
      />
      <AccidenteTrabajoModal
        opened={modalOpened}
        onClose={handleClose}
        accidente={editAccidente}
      />
    </Box>
  )
}
