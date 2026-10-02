'use client'

import { useState } from 'react'
import { Button, Select } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import { IconPlus, IconReportAnalytics, IconTruckDelivery } from '@tabler/icons-react'
import { useEppEntregas } from '@/features/sso/hooks/useEppEntregas'
import { RegistrarEntregaEppModal } from '@/features/sso/components/RegistrarEntregaEppModal'
import { ReporteEppModal } from '@/features/sso/components/ReporteEppModal'
import { columnasEppEntrega } from '@/features/sso/components/eppEntrega.columns'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useEquiposProteccion } from '@/features/sso/hooks/useEquiposProteccion'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar } from '@/components/ui'

export function EntregasEppView() {
  const compacto = useContainedInput('sm')
  const [page, setPage] = useState(1)
  const [servidorId, setServidorId] = useState<number | null>(null)
  const [equipoId, setEquipoId] = useState<string | null>(null)
  const [desde, setDesde] = useState('')
  const [hasta, setHasta] = useState('')
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [reporteOpened, { open: openReporte, close: closeReporte }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { data, isLoading, error, refetch } = useEppEntregas({
    page,
    servidor_id: servidorId ?? undefined,
    equipo_proteccion_id: equipoId ? Number(equipoId) : undefined,
    fecha_inicio: desde || undefined,
    fecha_fin: hasta || undefined,
  })
  const records = data?.data ?? []

  // El catálogo de equipos es corto y el selector no pagina: se pide entero
  // una vez para dar nombre a los ids del filtro.
  const { data: equipos } = useEquiposProteccion({ page: 1, estado: true })
  const equipoOptions = (equipos?.data ?? []).map((e) => ({ value: String(e.id), label: e.nombre }))

  const hayFiltros = Boolean(servidorId || equipoId || desde || hasta)

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
  }

  const limpiar = () => filtrar(() => {
    setServidorId(null); setEquipoId(null); setDesde(''); setHasta('')
  })

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
      {/* Una bitácora sin filtros solo sirve para leer lo último. El backend
          acepta servidor, equipo y rango de fechas desde que existe, y eran
          justo las tres preguntas que no se podían hacer: qué se le entregó a
          una persona, quién tiene tal equipo, qué se movió en un período. */}
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
          label="Equipo"
          placeholder="Todos"
          data={equipoOptions}
          searchable
          clearable
          style={{ minWidth: 200 }}
          {...compacto}
          value={equipoId}
          onChange={(v) => filtrar(() => setEquipoId(v))}
        />
        <DatePickerInput
          label="Desde"
          placeholder="Sin límite"
          valueFormat="DD/MM/YYYY"
          clearable
          style={{ minWidth: 150 }}
          {...compacto}
          value={toDateValue(desde)}
          onChange={(d) => filtrar(() => setDesde(fromDateValue(d ?? null)))}
        />
        <DatePickerInput
          label="Hasta"
          placeholder="Sin límite"
          valueFormat="DD/MM/YYYY"
          clearable
          style={{ minWidth: 150 }}
          {...compacto}
          value={toDateValue(hasta)}
          onChange={(d) => filtrar(() => setHasta(fromDateValue(d ?? null)))}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las entregas de EPP"
        errorHint="No quiere decir que no haya movimientos registrados: no se pudieron consultar."
        onRetry={() => refetch()}
        empty={!records.length}
        // Un vacío con filtros puestos no es el mismo vacío: ofrecer «registre
        // el primer movimiento» cuando lo que pasa es que el filtro no
        // encuentra nada invita a duplicar una entrega que ya está anotada.
        emptyProps={hayFiltros ? {
          icon: IconTruckDelivery,
          title: 'Ningún movimiento coincide con el filtro',
          description: 'Pruebe con otro servidor, otro equipo o un rango de fechas más amplio.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
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
          {...PAGINACION_ES}
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
