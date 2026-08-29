'use client'

import { useState } from 'react'
import { Box, Button, Group, Badge, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconReportAnalytics } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { useEppEntregas } from '../hooks/useEppEntregas'
import { RegistrarEntregaEppModal } from './RegistrarEntregaEppModal'
import { ReporteEppModal } from './ReporteEppModal'
import { MOTIVO_ENTREGA_COLORS, MOTIVO_ENTREGA_OPTIONS } from '../schemas/eppEntrega.schema'
import { formatFecha } from '@/lib/fecha'
import type { EppEntrega } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

export function EntregasEppTab() {
  const [page, setPage] = useState(1)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [reporteOpened, { open: openReporte, close: closeReporte }] = useDisclosure(false)

  const { data, isLoading } = useEppEntregas({ page })
  const records = data?.data ?? []

  const getMotivoLabel = (valor: string) =>
    MOTIVO_ENTREGA_OPTIONS.find(o => o.value === valor)?.label ?? valor

  const columns: DataTableColumn<EppEntrega>[] = [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (e) => (
        <Text size="sm" fw={500}>
          {e.servidor ? `${e.servidor.nombre} ${e.servidor.apellido}` : `Servidor ${e.servidor_id}`}
        </Text>
      ),
    },
    {
      accessor: 'equipo_proteccion',
      title: 'Equipo',
      render: (e) => e.equipo_proteccion?.nombre ?? `Equipo ${e.equipo_proteccion_id}`,
    },
    {
      accessor: 'fecha_entrega',
      title: 'Fecha',
      width: 110,
      render: (e) => formatFecha(e.fecha_entrega),
    },
    { accessor: 'cantidad', title: 'Cantidad', width: 90 },
    {
      accessor: 'motivo',
      title: 'Motivo',
      width: 130,
      render: (e) => (
        <Badge color={MOTIVO_ENTREGA_COLORS[e.motivo] ?? 'gray'} variant="light" size="sm">
          {getMotivoLabel(e.motivo)}
        </Badge>
      ),
    },
  ]

  return (
    <Box>
      <Group justify="flex-end" mb="md">
        <Button
          leftSection={<IconReportAnalytics size={16} />}
          variant="default"
          onClick={openReporte}
        >
          Lista de EPP entregados
        </Button>
        <Button
          leftSection={<IconPlus size={16} />}
          color="emerald"
          variant="light"
          onClick={open}
        >
          Registrar movimiento
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
      <RegistrarEntregaEppModal
        opened={modalOpened}
        onClose={close}
      />
      <ReporteEppModal
        opened={reporteOpened}
        onClose={closeReporte}
      />
    </Box>
  )
}
