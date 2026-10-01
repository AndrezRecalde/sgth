'use client'

import { useState } from 'react'
import { Button, Select, Stack, Text, Tooltip } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconFileCheck, IconPencil, IconPlus } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import {
  DataState, PAGINACION_ES, SgthTable, StatusBadge, TableActions, Toolbar,
} from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useVistosBuenos } from '../hooks/useDisciplinario'
import { VistoBuenoModal } from './VistoBuenoModal'
import { TransicionarVistoBuenoModal } from './TransicionarVistoBuenoModal'
import {
  CAUSAL_LABELS,
  TONO_VISTO_BUENO,
  ESTADO_VISTO_BUENO_LABELS,
  TRANSICIONES_VISTO_BUENO,
  nombreServidor,
  referenciaLegal,
} from '../utils/etiquetas'
import { formatFecha } from '@/lib/fecha'
import type { EstadoVistoBueno, VistoBueno } from '@/types/api'

const ESTADO_OPTIONS = (Object.keys(ESTADO_VISTO_BUENO_LABELS) as EstadoVistoBueno[])
  .map((e) => ({ value: e, label: ESTADO_VISTO_BUENO_LABELS[e] }))

const POR_PAGINA = 15

export function VistosBuenosTab() {
  const contained = useContainedInput()
  const [estado, setEstado] = useState<string | null>(null)
  const [page, setPage] = useState(1)
  const [seleccionado, setSeleccionado] = useState<VistoBueno | null>(null)
  const [crearOpened, { open: openCrear, close: closeCrear }] = useDisclosure(false)
  const [editarOpened, { open: openEditar, close: closeEditar }] = useDisclosure(false)

  // Cambiar el filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const cambiarEstado = (valor: string | null) => {
    setEstado(valor)
    setPage(1)
  }

  const { data, isLoading, error } = useVistosBuenos({
    estado: (estado as EstadoVistoBueno | null) ?? undefined,
    page,
    per_page: POR_PAGINA,
  })
  const tramites = data?.data ?? []

  const abrirTransicion = (tramite: VistoBueno) => {
    setSeleccionado(tramite)
    openEditar()
  }

  const columns: DataTableColumn<VistoBueno>[] = [
    {
      accessor: 'servidor',
      title: 'Trabajador',
      render: (t) => (
        <div>
          <Text size="sm" fw={500}>{nombreServidor(t.servidor)}</Text>
          <Text size="xs" c="dimmed">{t.servidor?.cedula ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'causal',
      title: 'Causal',
      render: (t) => (
        <Tooltip label={referenciaLegal(t.causal)} withArrow>
          <Text size="sm" lineClamp={2}>{CAUSAL_LABELS[t.causal]}</Text>
        </Tooltip>
      ),
    },
    {
      accessor: 'numero_tramite_mdt',
      title: 'Trámite MDT',
      width: 150,
      render: (t) => (
        <Text size="sm" ff="monospace">{t.numero_tramite_mdt ?? '—'}</Text>
      ),
    },
    {
      accessor: 'fecha_solicitud',
      title: 'Solicitud',
      width: 110,
      render: (t) => <Text size="sm">{formatFecha(t.fecha_solicitud)}</Text>,
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 160,
      render: (t) => (
        <StatusBadge tone={TONO_VISTO_BUENO[t.estado]}>
          {ESTADO_VISTO_BUENO_LABELS[t.estado]}
        </StatusBadge>
      ),
    },
    {
      accessor: 'movimiento_personal',
      title: 'Cesación',
      width: 130,
      render: (t) => t.movimiento_personal
        ? (
          <StatusBadge>
            {t.movimiento_personal.codigo_registro ?? 'En borrador'}
          </StatusBadge>
        )
        : <Text size="sm" c="dimmed">—</Text>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (t) => {
        if (TRANSICIONES_VISTO_BUENO[t.estado].length === 0) return null

        return (
          <TableActions
            actions={[
              {
                label: 'Actualizar trámite',
                icon: <IconPencil size={14} />,
                onClick: () => abrirTransicion(t),
              },
            ]}
          />
        )
      },
    },
  ]

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <Button
            leftSection={<IconPlus size={16} />}
            variant="light"
            onClick={openCrear}
          >
            Solicitar visto bueno
          </Button>
        }
      >
        <Select
          label="Estado"
          placeholder="Todos"
          data={ESTADO_OPTIONS}
          value={estado}
          onChange={cambiarEstado}
          clearable
          {...contained}
          style={{ minWidth: 240 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!tramites.length}
        emptyProps={{
          icon: IconFileCheck,
          title: 'Sin trámites de visto bueno',
          description: estado
            ? 'Ningún trámite se encuentra en ese estado.'
            : 'No hay trámites de visto bueno registrados.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={tramites}
          columns={columns}
          totalRecords={data?.total ?? tramites.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <VistoBuenoModal opened={crearOpened} onClose={closeCrear} />
      <TransicionarVistoBuenoModal
        opened={editarOpened}
        onClose={closeEditar}
        tramite={seleccionado}
      />
    </Stack>
  )
}
