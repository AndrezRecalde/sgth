'use client'

import { useState } from 'react'
import { Badge, Select, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconEye, IconFileDescription } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, SgthTable, TableActions, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useBandejaMovimientos } from '../hooks/useMovimientoMutations'
import { AccionPersonalDetalleDrawer } from './AccionPersonalDetalleDrawer'
import {
  ESTADO_COLORS, ESTADO_LABELS,
} from '../utils/estadoAccionPersonal'
import { SUBTIPO_LABELS, etiquetaTipoMovimiento } from '../utils/taxonomiaAccionPersonal'
import type { EstadoAccionPersonal, MovimientoPersonal } from '@/types/api'

const ESTADO_OPTIONS = (Object.keys(ESTADO_LABELS) as EstadoAccionPersonal[])
  .map((e) => ({ value: e, label: ESTADO_LABELS[e] }))

function nombreServidor(s?: MovimientoPersonal['servidor']): string {
  if (!s) return '—'

  return [s.apellido, s.segundo_apellido, s.nombre, s.segundo_nombre]
    .filter(Boolean).join(' ') || '—'
}

function formatFecha(fecha?: string | null): string {
  if (!fecha) return '—'

  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

/**
 * Bandeja transversal de acciones de personal. Existe para revisar y aprobar
 * sin entrar expediente por expediente: por defecto muestra los borradores,
 * que son los que esperan decisión de Talento Humano.
 */
export function BandejaAccionesPersonal() {
  const contained = useContainedInput()
  const [estado, setEstado] = useState<string | null>('borrador')
  const [seleccionadoId, setSeleccionadoId] = useState<number | null>(null)
  const [detalleOpened, { open: abrirDetalle, close: cerrarDetalle }] = useDisclosure(false)

  const { data, isLoading, error } = useBandejaMovimientos(
    estado ? { estado } : undefined,
  )
  const acciones = data?.data ?? []

  const columns: DataTableColumn<MovimientoPersonal>[] = [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (m) => {
        const s = m.servidor
        return (
          <div>
            <Text size="sm" fw={500}>{nombreServidor(s)}</Text>
            <Text size="xs" c="dimmed">{s?.cedula ?? '—'}</Text>
          </div>
        )
      },
    },
    {
      accessor: 'tipo_movimiento',
      title: 'Acción',
      render: (m) => (
        <div>
          <Text size="sm">
            {etiquetaTipoMovimiento(m.tipo_movimiento)}
          </Text>
          {m.subtipo_movimiento && (
            <Text size="xs" c="dimmed">
              {SUBTIPO_LABELS[m.subtipo_movimiento as keyof typeof SUBTIPO_LABELS]
                ?? m.subtipo_movimiento}
            </Text>
          )}
        </div>
      ),
    },
    {
      accessor: 'fecha_efectiva',
      title: 'Rige desde',
      width: 110,
      render: (m) => <Text size="sm">{formatFecha(m.fecha_efectiva)}</Text>,
    },
    {
      accessor: 'codigo_registro',
      title: 'Código',
      width: 130,
      render: (m) => (
        <Text size="sm" ff="monospace">{m.codigo_registro ?? '—'}</Text>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 150,
      render: (m) => m.estado
        ? (
          <Badge color={ESTADO_COLORS[m.estado]} variant="light" size="sm">
            {ESTADO_LABELS[m.estado]}
          </Badge>
        )
        : <Text size="sm" c="dimmed">—</Text>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      // Una sola entrada: revisar primero, decidir después. Editar, avanzar,
      // anular y descargar viven dentro del drawer, sobre la acción completa.
      render: (m) => (
        <TableActions
          actions={[
            {
              label: 'Ver detalle',
              icon: <IconEye size={14} />,
              color: 'blue',
              onClick: () => {
                setSeleccionadoId(Number(m.id))
                abrirDetalle()
              },
            },
          ]}
        />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <Text size="sm" c="dimmed">
            {acciones.length} acción(es) en la vista
          </Text>
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
          style={{ minWidth: 260 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!acciones.length}
        emptyProps={{
          icon: IconFileDescription,
          title: 'Sin acciones de personal',
          description: estado
            ? 'Ninguna acción se encuentra en ese estado.'
            : 'No hay acciones de personal registradas.',
        }}
      >
        <SgthTable
          records={acciones}
          columns={columns}
          minHeight={200}
        />
      </DataState>

      <AccionPersonalDetalleDrawer
        opened={detalleOpened}
        onClose={cerrarDetalle}
        movimientoId={seleccionadoId}
      />
    </Stack>
  )
}
