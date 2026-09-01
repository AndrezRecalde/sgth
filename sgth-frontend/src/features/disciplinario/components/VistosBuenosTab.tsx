'use client'

import { useState } from 'react'
import { Badge, Button, Select, Stack, Text, Tooltip } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconFileCheck, IconPencil, IconPlus } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, SgthTable, TableActions, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useVistosBuenos } from '../hooks/useDisciplinario'
import { VistoBuenoModal } from './VistoBuenoModal'
import { TransicionarVistoBuenoModal } from './TransicionarVistoBuenoModal'
import {
  CAUSAL_LABELS,
  ESTADO_VISTO_BUENO_COLORS,
  ESTADO_VISTO_BUENO_LABELS,
  TRANSICIONES_VISTO_BUENO,
  formatFecha,
  nombreServidor,
  referenciaLegal,
} from '../utils/etiquetas'
import type { EstadoVistoBueno, VistoBueno } from '@/types/api'

const ESTADO_OPTIONS = (Object.keys(ESTADO_VISTO_BUENO_LABELS) as EstadoVistoBueno[])
  .map((e) => ({ value: e, label: ESTADO_VISTO_BUENO_LABELS[e] }))

export function VistosBuenosTab() {
  const contained = useContainedInput()
  const [estado, setEstado] = useState<string | null>(null)
  const [seleccionado, setSeleccionado] = useState<VistoBueno | null>(null)
  const [crearOpened, { open: openCrear, close: closeCrear }] = useDisclosure(false)
  const [editarOpened, { open: openEditar, close: closeEditar }] = useDisclosure(false)

  const { data, isLoading, error } = useVistosBuenos(
    estado ? { estado: estado as EstadoVistoBueno } : undefined,
  )
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
        <Badge color={ESTADO_VISTO_BUENO_COLORS[t.estado]} variant="light" size="sm">
          {ESTADO_VISTO_BUENO_LABELS[t.estado]}
        </Badge>
      ),
    },
    {
      accessor: 'movimiento_personal',
      title: 'Cesación',
      width: 130,
      render: (t) => t.movimiento_personal
        ? (
          <Badge color="violet" variant="light" size="sm">
            {t.movimiento_personal.codigo_registro ?? 'En borrador'}
          </Badge>
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
                color: 'blue',
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
            color="emerald"
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
          onChange={setEstado}
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
          records={tramites}
          columns={columns}
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
