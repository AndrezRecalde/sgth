'use client'

import { useState } from 'react'
import { Alert, Progress, Select, Stack, Text } from '@mantine/core'
import { IconInfoCircle, IconList, IconUserMinus } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, PAGINACION_ES, SgthTable, StatusBadge, TableActions, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { formatFecha } from '@/lib/fecha'
import { useDesvinculaciones } from '../hooks/useDesvinculaciones'
import type { Desvinculacion, FiltrosDesvinculaciones } from '../services/desvinculacionService'
import { DesvinculacionDetalleModal } from './DesvinculacionDetalleModal'

const POR_PAGINA = 15

const ESTADOS = [
  { value: 'abierta', label: 'Abiertas' },
  { value: 'cerrada', label: 'Completas' },
  { value: 'cancelada', label: 'Canceladas' },
  { value: 'todas', label: 'Todas' },
]

const TONO = { abierta: 'warning', cerrada: 'success', cancelada: 'neutral' } as const
const ETIQUETA = { abierta: 'Abierta', cerrada: 'Completa', cancelada: 'Cancelada' } as const

/**
 * Lo que pasa después de la salida (diseño, 9.9; fase 7.4a): una
 * desvinculación por cada cese que deja a la persona sin vínculo, con sus
 * pasos. Las abiertas primero.
 */
export function DesvinculacionesPanel() {
  const contained = useContainedInput('sm')
  const [estado, setEstado] = useState<NonNullable<FiltrosDesvinculaciones['estado']>>('abierta')
  const [pagina, setPagina] = useState(1)
  const [verId, setVerId] = useState<number | null>(null)
  const { data, isLoading, error, refetch } = useDesvinculaciones({ estado, page: pagina, per_page: POR_PAGINA })
  const filas = data?.data ?? []

  const columnas: DataTableColumn<Desvinculacion>[] = [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (d) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{d.servidor}</Text>
          <Text size="xs" c="dimmed">{d.cedula ?? '—'}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'fecha_salida',
      title: 'Salida',
      render: (d) => (
        <Stack gap={0}>
          <Text size="sm">{formatFecha(d.fecha_salida)}</Text>
          <Text size="xs" c="dimmed">{d.causal ?? 'Cese'}{d.acto ? ` · ${d.acto}` : ''}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'pasos_hechos',
      title: 'Pasos',
      width: 160,
      render: (d) => (
        <Stack gap={2}>
          <Text size="xs">{d.pasos_hechos} de {d.pasos_total}</Text>
          <Progress value={(d.pasos_hechos / Math.max(d.pasos_total, 1)) * 100} size="sm" />
        </Stack>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      render: (d) => <StatusBadge tone={TONO[d.estado]}>{ETIQUETA[d.estado]}</StatusBadge>,
    },
    {
      accessor: 'acciones',
      title: '',
      render: (d) => (
        <TableActions actions={[{ label: 'Ver sus pasos', icon: <IconList size={14} />, onClick: () => setVerId(d.id) }]} />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Cuando un cese deja a la persona sin vínculo, empieza su desvinculación: la liquidación y la indemnización, que
        marca el Director; la devolución de los bienes y la baja del usuario, que hace Tecnologías de la Información; el
        certificado laboral y la declaración patrimonial de fin de gestión. Lo que otro módulo registra queda hecho solo.
      </Alert>

      <Toolbar>
        <Select
          label="Estado"
          data={ESTADOS}
          value={estado}
          onChange={(v) => { setEstado((v ?? 'abierta') as typeof estado); setPagina(1) }}
          allowDeselect={false}
          {...contained}
          style={{ minWidth: 200 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las desvinculaciones"
        onRetry={refetch}
        empty={!filas.length}
        page={pagina}
        emptyProps={{
          icon: IconUserMinus,
          title: 'Sin desvinculaciones',
          description: estado === 'abierta'
            ? 'Nadie tiene pasos pendientes de su salida.'
            : 'Ninguna desvinculación coincide con el filtro.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={filas}
          columns={columnas}
          totalRecords={data?.total ?? 0}
          recordsPerPage={POR_PAGINA}
          page={pagina}
          onPageChange={setPagina}
          minHeight={200}
        />
      </DataState>

      <DesvinculacionDetalleModal desvinculacionId={verId} onClose={() => setVerId(null)} />
    </Stack>
  )
}
