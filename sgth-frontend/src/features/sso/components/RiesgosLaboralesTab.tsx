'use client'

import { confirmar } from '@/components/ui'
import { useState } from 'react'
import { Box, Button, Group, Badge, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconEdit, IconTrash, IconList } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { useRiesgosLaborales, useRiesgoLaboralMutations } from '../hooks/useRiesgosLaborales'
import { RiesgoLaboralModal } from './RiesgoLaboralModal'
import { FactoresRiesgoModal } from './FactoresRiesgoModal'
import { NIVEL_INTERVENCION_LABELS, NIVEL_INTERVENCION_COLORS } from '../schemas/riesgoLaboral.schema'
import type { RiesgoLaboral } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

export function RiesgosLaboralesTab() {
  const [page, setPage] = useState(1)
  const [editRiesgo, setEditRiesgo] = useState<RiesgoLaboral | null>(null)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [factoresOpened, { open: openFactores, close: closeFactores }] = useDisclosure(false)

  const { eliminar } = useRiesgoLaboralMutations()
  const { data, isLoading } = useRiesgosLaborales({ page })
  const records = data?.data ?? []

  const handleEdit = (riesgo: RiesgoLaboral) => {
    setEditRiesgo(riesgo)
    open()
  }

  const handleClose = () => {
    setEditRiesgo(null)
    close()
  }

  const columns: DataTableColumn<RiesgoLaboral>[] = [
    {
      accessor: 'puesto',
      title: 'Puesto',
      render: (r) => (
        <Text size="sm" fw={500}>{r.puesto?.cargo?.nombre ?? `Puesto ${r.puesto_id}`}</Text>
      ),
    },
    {
      accessor: 'factor_riesgo',
      title: 'Factor de riesgo',
      render: (r) => r.factor_riesgo?.nombre ?? `Factor ${r.factor_riesgo_id}`,
    },
    {
      accessor: 'nivel_riesgo_valor',
      title: 'NR',
      width: 70,
      render: (r) => r.nivel_riesgo_valor,
    },
    {
      accessor: 'nivel_intervencion',
      title: 'Nivel de intervención',
      width: 200,
      render: (r) => (
        <Badge color={NIVEL_INTERVENCION_COLORS[r.nivel_intervencion] ?? 'gray'} variant="light" size="sm">
          {NIVEL_INTERVENCION_LABELS[r.nivel_intervencion] ?? r.nivel_intervencion}
        </Badge>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 90,
      render: (r) => (
        <Badge color={r.estado ? 'emerald' : 'gray'} variant="light" size="sm">
          {r.estado ? 'Activo' : 'Inactivo'}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (riesgo) => (
        <TableActions
          actions={[
            {
              label: 'Editar riesgo',
              icon: <IconEdit size={14} />,
              color: 'blue',
              onClick: () => handleEdit(riesgo),
            },
            {
              label: 'Eliminar riesgo',
              icon: <IconTrash size={14} />,
              color: 'red',
              onClick: () => confirmar({
                title:   'Eliminar riesgo laboral',
                message: 'Se eliminará este riesgo laboral y su valoración. No se puede deshacer.',
                destructiva: true,
                onConfirm: () => eliminar.mutate(riesgo.id),
              }),
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
          leftSection={<IconList size={16} />}
          variant="default"
          onClick={openFactores}
        >
          Catálogo de factores
        </Button>
        <Button
          leftSection={<IconPlus size={16} />}
          color="emerald"
          variant="light"
          onClick={() => { setEditRiesgo(null); open() }}
        >
          Nuevo riesgo
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
      <RiesgoLaboralModal
        opened={modalOpened}
        onClose={handleClose}
        riesgo={editRiesgo}
      />
      <FactoresRiesgoModal
        opened={factoresOpened}
        onClose={closeFactores}
      />
    </Box>
  )
}
