'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import { IconPlus, IconReportAnalytics, IconTruckDelivery } from '@tabler/icons-react'
import { useEppEntregas } from '@/features/sso/hooks/useEppEntregas'
import { RegistrarEntregaEppModal } from '@/features/sso/components/RegistrarEntregaEppModal'
import { ReporteEppModal } from '@/features/sso/components/ReporteEppModal'
import { columnasEppEntrega } from '@/features/sso/components/eppEntrega.columns'
import { DataState, PageHeader, PageShell, SgthTable } from '@/components/ui'

export function EntregasEppView() {
  const [page, setPage] = useState(1)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [reporteOpened, { open: openReporte, close: closeReporte }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { data, isLoading, error, refetch } = useEppEntregas({ page })
  const records = data?.data ?? []

  return (
    <PageShell>
      <PageHeader
        title="Entregas de EPP"
        description="Bitácora de entregas, devoluciones y reposiciones de equipo de protección"
        // La acción principal de la pantalla va aquí y no flotando sobre la
        // tabla, que es donde estaba (regla 05). El reporte lo ve cualquiera
        // que entre al módulo; registrar un movimiento, solo quien gestiona.
        actions={
          <>
            <Button
              leftSection={<IconReportAnalytics size={16} />}
              variant="default"
              onClick={openReporte}
            >
              Lista de EPP entregados
            </Button>
            {puedeGestionar && (
              <Button
                leftSection={<IconPlus size={16} />}
                variant="light"
                onClick={open}
              >
                Registrar movimiento
              </Button>
            )}
          </>
        }
      />
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
          action: puedeGestionar ? (
            <Button variant="light" leftSection={<IconPlus size={16} />} onClick={open}>
              Registrar movimiento
            </Button>
          ) : undefined,
        }}
        page={page}
      >
        <SgthTable
          records={records}
          columns={columnasEppEntrega}
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
    </PageShell>
  )
}
