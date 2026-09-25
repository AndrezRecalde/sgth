'use client'

import { useState } from 'react'
import { Box, Button, Group, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconReportAnalytics, IconTruckDelivery } from '@tabler/icons-react'
import { useEppEntregas } from '../hooks/useEppEntregas'
import { RegistrarEntregaEppModal } from './RegistrarEntregaEppModal'
import { ReporteEppModal } from './ReporteEppModal'
import { MOTIVO_ENTREGA_OPTIONS } from '../schemas/eppEntrega.schema'
import { formatFecha } from '@/lib/fecha'
import type { EppEntrega } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, SgthTable, StatusBadge } from '@/components/ui'

export function EntregasEppTab() {
  const [page, setPage] = useState(1)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [reporteOpened, { open: openReporte, close: closeReporte }] = useDisclosure(false)

  const { data, isLoading, error, refetch } = useEppEntregas({ page })
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
        <StatusBadge>
          {getMotivoLabel(e.motivo)}
        </StatusBadge>
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
          variant="light"
          onClick={open}
        >
          Registrar movimiento
        </Button>
      </Group>
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las entregas de EPP"
        errorHint="No quiere decir que no haya movimientos registrados: no se pudieron consultar."
        onRetry={() => refetch()}
        empty={!records.length}
        emptyProps={{
          icon: IconTruckDelivery,
          title: 'Sin movimientos de EPP',
          description: 'Aún no se ha registrado ninguna entrega, devolución ni reposición.',
          action: (
            <Button variant="light" leftSection={<IconPlus size={16} />} onClick={open}>
              Registrar movimiento
            </Button>
          ),
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
