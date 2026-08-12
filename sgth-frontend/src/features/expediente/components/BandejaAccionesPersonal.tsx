'use client'

import { useState } from 'react'
import { Badge, Box, Group, Select, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconEye } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
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

function nombreServidor(s?: Record<string, unknown> | null): string {
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

  const { data, isLoading } = useBandejaMovimientos(
    estado ? { estado } : undefined,
  )
  const acciones = data?.data ?? []

  const columns: DataTableColumn<MovimientoPersonal>[] = [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (m) => {
        const s = (m as unknown as { servidor?: Record<string, unknown> }).servidor
        return (
          <div>
            <Text size="sm" fw={500}>{nombreServidor(s)}</Text>
            <Text size="xs" c="dimmed">{(s?.cedula as string) ?? '—'}</Text>
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
    <Box>
      <Group justify="space-between" mb="md">
        <Select
          label="Filtrar por estado"
          placeholder="Todos"
          data={ESTADO_OPTIONS}
          value={estado}
          onChange={setEstado}
          clearable
          {...contained}
          style={{ minWidth: 260 }}
        />
        <Text size="sm" c="dimmed">
          {acciones.length} acción(es) en la vista
        </Text>
      </Group>

      <SgthTable
        records={acciones}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
      />

      <AccionPersonalDetalleDrawer
        opened={detalleOpened}
        onClose={cerrarDetalle}
        movimientoId={seleccionadoId}
      />
    </Box>
  )
}
