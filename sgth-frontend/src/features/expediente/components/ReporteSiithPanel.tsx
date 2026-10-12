'use client'

import { useState } from 'react'
import { Alert, Button, SegmentedControl, Select, Stack, Switch, Text } from '@mantine/core'
import { MonthPickerInput } from '@mantine/dates'
import { IconCheck, IconDownload, IconFileReport, IconInfoCircle, IconSettings } from '@tabler/icons-react'
import dayjs from 'dayjs'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, SgthModal, SgthTable, StatusBadge, Toolbar, confirmar, notificar } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { guardarArchivo } from '@/lib/archivo'
import { formatFecha } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { dinero } from '../utils/dinero'
import {
  useActualizarConfiguracionSiith, useConfiguracionSiith, useReporteSiith, useTranscribir,
} from '../hooks/useReporteSiith'
import {
  reporteSiithService, type ActoDelReporte, type ClaseDelReporte, type PortalReporte, type SituacionReporte,
} from '../services/reporteSiithService'

const SITUACIONES = [
  { value: 'pendientes', label: 'Pendientes de transcribir' },
  { value: 'transcritos', label: 'Transcritos' },
  { value: 'todos', label: 'Todo lo registrado en el mes' },
]

/**
 * El reporte de apoyo a la transcripción al SIITH y al SUT (diseño, 9.12; fase
 * 7.5). Los portales del Ministerio no tienen API: se transcribe a mano, y
 * esto ordena lo que hay que transcribir y lleva la cuenta de lo transcrito.
 */
export function ReporteSiithPanel() {
  const contained = useContainedInput('sm')
  const { hasPermiso } = useAuth()
  const esDirector = hasPermiso('registrar-accion-personal')
  const [portal, setPortal] = useState<PortalReporte>('siith')
  const [periodo, setPeriodo] = useState(dayjs().format('YYYY-MM'))
  const [situacion, setSituacion] = useState<SituacionReporte>('pendientes')
  const [seleccion, setSeleccion] = useState<ActoDelReporte[]>([])
  const [descargando, setDescargando] = useState(false)
  const [configurando, setConfigurando] = useState(false)
  const { data, isLoading, error, refetch } = useReporteSiith(portal, periodo, situacion)
  const transcribir = useTranscribir()
  const actos = data?.actos ?? []
  const marcables = seleccion.filter((a) => !a.transcrito || a.anulacion_pendiente)

  const descargar = async () => {
    setDescargando(true)
    try {
      guardarArchivo(await reporteSiithService.excel({ portal, periodo, situacion }), `reporte_${portal}_${periodo}.xlsx`)
    } catch (e) {
      notificar.error('No se pudo descargar el Excel', getApiErrorMessage(e))
    } finally {
      setDescargando(false)
    }
  }

  const columnas: DataTableColumn<ActoDelReporte>[] = [
    {
      accessor: 'numero',
      title: 'Acto',
      render: (a) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{a.numero}</Text>
          <Text size="xs" c="dimmed">Registro {formatFecha(a.fecha_registro)} · rige {formatFecha(a.fecha_efectiva)}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (a) => (
        <Stack gap={0}>
          <Text size="sm">{a.servidor}</Text>
          <Text size="xs" c="dimmed">{a.cedula ?? '—'}{a.modalidad ? ` · ${a.modalidad}` : ''}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'clase',
      title: 'Clase',
      render: (a) => (
        <Stack gap={0}>
          <Text size="sm">{a.clase}</Text>
          {a.causal && <Text size="xs" c="dimmed">{a.causal}</Text>}
        </Stack>
      ),
    },
    {
      accessor: 'destino',
      title: 'De → a',
      render: (a) => (
        <Text size="xs">
          {[a.origen.puesto, a.origen.grado, a.origen.unidad].filter(Boolean).join(' · ') || '—'}
          {a.origen.remuneracion != null ? ` (${dinero(a.origen.remuneracion)})` : ''}
          {' → '}
          {[a.destino.puesto, a.destino.grado, a.destino.unidad].filter(Boolean).join(' · ') || '—'}
          {a.destino.remuneracion != null ? ` (${dinero(a.destino.remuneracion)})` : ''}
        </Text>
      ),
    },
    {
      accessor: 'transcrito',
      title: 'Situación',
      render: (a) => a.anulacion_pendiente
        ? <StatusBadge tone="danger">Anulada: anular en el portal</StatusBadge>
        : a.transcrito
          ? <StatusBadge tone="success">Transcrito</StatusBadge>
          : <StatusBadge tone="warning">Pendiente</StatusBadge>,
    },
  ]

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Lo que hay que transcribir al portal del Ministerio: al SIITH, las acciones de personal, los contratos de los
        ocasionales y los actos de las autoridades; al SUT, las hojas de los obreros. Entra lo registrado en el mes, y lo
        que no se transcribió sigue pendiente en los siguientes. Si un acto ya transcrito se anula, vuelve para anularlo
        también allá.
      </Alert>

      <Toolbar
        actions={
          <>
            <Button variant="default" leftSection={<IconSettings size={16} />} onClick={() => setConfigurando(true)}>
              Qué se reporta
            </Button>
            <Button variant="default" leftSection={<IconDownload size={16} />} loading={descargando} onClick={descargar}>
              Excel
            </Button>
            <Button
              leftSection={<IconCheck size={16} />}
              disabled={!marcables.length}
              loading={transcribir.isPending}
              onClick={() => confirmar({
                title: 'Marcar como transcritos',
                message: `Se marcan ${marcables.length} acto(s) como ya transcritos al ${portal.toUpperCase()}: dejan de salir en los pendientes.`,
                confirmLabel: 'Marcar',
                onConfirm: () => transcribir.mutate(
                  { portal, ids: marcables.map((a) => a.movimiento_id) },
                  { onSuccess: () => setSeleccion([]) },
                ),
              })}
            >
              Marcar como transcritos{marcables.length ? ` (${marcables.length})` : ''}
            </Button>
          </>
        }
      >
        <SegmentedControl
          data={[{ value: 'siith', label: 'SIITH' }, { value: 'sut', label: 'SUT' }]}
          value={portal}
          onChange={(v) => { setPortal(v as PortalReporte); setSeleccion([]) }}
        />
        <MonthPickerInput
          label="Mes"
          valueFormat="MMMM [de] YYYY"
          value={`${periodo}-01`}
          onChange={(v) => { if (v) { setPeriodo(dayjs(v).format('YYYY-MM')); setSeleccion([]) } }}
          {...contained}
          style={{ minWidth: 190 }}
        />
        <Select
          label="Situación"
          data={SITUACIONES}
          value={situacion}
          onChange={(v) => { setSituacion((v ?? 'pendientes') as SituacionReporte); setSeleccion([]) }}
          allowDeselect={false}
          {...contained}
          style={{ minWidth: 230 }}
        />
      </Toolbar>

      {data && data.resumen.anulaciones_pendientes > 0 && (
        <Alert variant="light" color="red" icon={<IconInfoCircle size={16} />}>
          {data.resumen.anulaciones_pendientes === 1 ? 'Un acto transcrito se anuló' : `${data.resumen.anulaciones_pendientes} actos transcritos se anularon`}:
          anúlelos también en el {portal.toUpperCase()} y márquelos.
        </Alert>
      )}

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar el reporte"
        onRetry={refetch}
        empty={!actos.length}
        emptyProps={{
          icon: IconFileReport,
          title: situacion === 'pendientes' ? 'Nada pendiente de transcribir' : 'Sin actos',
          description: situacion === 'pendientes'
            ? `Todo lo registrado hasta ${data?.etiqueta ?? 'este mes'} ya está en el ${portal.toUpperCase()}.`
            : 'Ningún acto de las clases que se reportan se registró en el mes.',
        }}
      >
        <SgthTable
          records={actos}
          columns={columnas}
          idAccessor="movimiento_id"
          selectedRecords={seleccion}
          onSelectedRecordsChange={setSeleccion}
          isRecordSelectable={(a) => !a.transcrito || a.anulacion_pendiente}
          minHeight={200}
        />
      </DataState>

      <ConfiguracionModal opened={configurando} onClose={() => setConfigurando(false)} puedeCambiar={esDirector} />
    </Stack>
  )
}

/** Qué clase se reporta a cada portal; las marcas las cambia el Director. */
function ConfiguracionModal({ opened, onClose, puedeCambiar }: { opened: boolean; onClose: () => void; puedeCambiar: boolean }) {
  const { data = [], isLoading, error, refetch } = useConfiguracionSiith(opened)
  const actualizar = useActualizarConfiguracionSiith()

  const columnas: DataTableColumn<ClaseDelReporte>[] = [
    {
      accessor: 'etiqueta',
      title: 'Clase',
      render: (c) => (
        <Stack gap={0}>
          <Text size="sm">{c.etiqueta}</Text>
          <Text size="xs" c="dimmed">{c.familia}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'reportable_siith',
      title: 'SIITH',
      width: 80,
      render: (c) => (
        <Switch
          aria-label={`Reportar ${c.etiqueta} al SIITH`}
          checked={c.reportable_siith}
          disabled={!puedeCambiar || actualizar.isPending}
          onChange={(e) => actualizar.mutate({ clase: c.clase, marcas: { reportable_siith: e.currentTarget.checked } })}
        />
      ),
    },
    {
      accessor: 'reportable_sut',
      title: 'SUT',
      width: 80,
      render: (c) => (
        <Switch
          aria-label={`Reportar ${c.etiqueta} al SUT`}
          checked={c.reportable_sut}
          disabled={!puedeCambiar || actualizar.isPending}
          onChange={(e) => actualizar.mutate({ clase: c.clase, marcas: { reportable_sut: e.currentTarget.checked } })}
        />
      ),
    },
  ]

  return (
    <SgthModal opened={opened} onClose={onClose} title="Qué se reporta" size="lg">
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          Las marcas iniciales son una propuesta: Talento Humano las coteja con el formato del Ministerio.
          {puedeCambiar ? '' : ' Las cambia el Director de Talento Humano.'}
        </Text>
        <DataState loading={isLoading} error={error} errorTitle="No se pudo cargar la configuración" onRetry={refetch}>
          <SgthTable records={data} columns={columnas} idAccessor="clase" minHeight={200} />
        </DataState>
      </Stack>
    </SgthModal>
  )
}
