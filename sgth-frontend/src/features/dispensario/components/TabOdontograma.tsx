'use client'

import { Stack, Skeleton, Divider, Grid, Text, Badge, Button, Group } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useState } from 'react'
import { IconDental, IconBan } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { EmptyState } from '@/components/ui/EmptyState'
import { useAuth } from '@/hooks/useAuth'
import { useOdontograma } from '../hooks/useOdontograma'
import { OdontogramaChart } from './odontograma/OdontogramaChart'
import { RegistrarProcedimientoModal } from './odontograma/RegistrarProcedimientoModal'
import { AnularProcedimientoModal } from './odontograma/AnularProcedimientoModal'
import {
  PROCEDIMIENTO_OPTIONS, SUPERFICIE_OPTIONS,
} from '../services/odontogramaService'
import type {
  OdontogramaPieza, OdontogramaProcedimientoDetalle,
} from '../services/odontogramaService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  historiaClinicaId: number
  consultaMedicaId?: number | null
}

interface HistorialItem extends OdontogramaProcedimientoDetalle {
  numero_pieza: number
}

interface HistorialDetalleProps {
  item:        HistorialItem
  puedeAnular: boolean
  onAnular:    () => void
}

function HistorialDetalle({ item, puedeAnular, onAnular }: HistorialDetalleProps) {
  const getLabelSuperficie = (valor: string) =>
    SUPERFICIE_OPTIONS.find(o => o.value === valor)?.label ?? valor

  return (
    <Stack
      gap="xs"
      p="md"
      style={{
        background: 'var(--mantine-color-default-hover)',
        borderTop: '1px solid var(--mantine-color-default-border)',
      }}
    >
      <Grid>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Text size="xs" c="dimmed" mb={2}>Superficie</Text>
          <Text size="sm" fw={500}>
            {item.superficie ? getLabelSuperficie(item.superficie) : '—'}
          </Text>
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <Text size="xs" c="dimmed" mb={2}>Observaciones</Text>
          <Text size="sm" fw={500}>
            {item.observaciones ?? '—'}
          </Text>
        </Grid.Col>
      </Grid>

      {item.anulado_en ? (
        <Text size="xs" c="red">
          Anulado por {item.anulado_por?.nombre_completo ?? item.anulado_por?.usuario_ti ?? '—'}
          {': '}{item.motivo_anulacion}
        </Text>
      ) : puedeAnular ? (
        <Group justify="flex-end">
          <Button
            size="xs"
            variant="light"
            color="red"
            leftSection={<IconBan size={13} />}
            onClick={onAnular}
          >
            Anular procedimiento
          </Button>
        </Group>
      ) : null}
    </Stack>
  )
}

export function TabOdontograma({ historiaClinicaId, consultaMedicaId }: Props) {
  const { usuario } = useAuth()
  const { data: odontograma, isLoading } = useOdontograma(historiaClinicaId)
  const [modalOpened, { open: openModal, close: closeModal }] = useDisclosure(false)
  const [piezaSeleccionada, setPiezaSeleccionada] =
    useState<OdontogramaPieza | null>(null)
  const [anularModalOpened, { open: openAnularModal, close: closeAnularModal }] =
    useDisclosure(false)
  const [itemParaAnular, setItemParaAnular] =
    useState<HistorialItem | null>(null)

  const handleSeleccionarPieza = (pieza: OdontogramaPieza) => {
    setPiezaSeleccionada(pieza)
    openModal()
  }

  const handleAnular = (item: HistorialItem) => {
    setItemParaAnular(item)
    openAnularModal()
  }

  const getLabelProcedimiento = (valor: string) =>
    PROCEDIMIENTO_OPTIONS.find(o => o.value === valor)?.label ?? valor

  const esAnulable = (item: HistorialItem): boolean => {
    if (item.anulado_en) return false
    if (!usuario || item.realizado_por?.id !== usuario.id) return false

    if (item.consulta_medica_id != null) {
      return item.consulta_medica_id === consultaMedicaId
    }
    return new Date(item.created_at).toDateString() === new Date().toDateString()
  }

  if (isLoading || !odontograma) {
    return (
      <Stack gap="sm" p="md">
        <Skeleton height={180} radius="md" />
      </Stack>
    )
  }

  const mostrarTemporal = odontograma.piezas.some(
    p => p.denticion === 'temporal'
  )

  const historial: HistorialItem[] = odontograma.piezas
    .flatMap(p => (p.procedimientos ?? []).map(proc => ({
      ...proc,
      numero_pieza: p.numero_pieza,
    })))
    .sort((a, b) => b.fecha.localeCompare(a.fecha))
    .slice(0, 15)

  const columns: DataTableColumn<HistorialItem>[] = [
    {
      accessor: 'numero_pieza',
      title: 'Pieza',
      width: 70,
    },
    {
      accessor: 'procedimiento',
      title: 'Procedimiento',
      render: (item) => (
        <Group gap={6} wrap="nowrap">
          <Text
            size="sm"
            td={item.anulado_en ? 'line-through' : undefined}
            c={item.anulado_en ? 'dimmed' : undefined}
          >
            {getLabelProcedimiento(item.procedimiento)}
          </Text>
          {item.anulado_en && (
            <Badge size="xs" variant="light" color="red">Anulado</Badge>
          )}
        </Group>
      ),
    },
    {
      accessor: 'fecha',
      title: 'Fecha',
      width: 110,
      render: (item) => new Date(item.fecha).toLocaleDateString('es-EC', {
        day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
      }),
    },
    {
      accessor: 'realizado_por',
      title: 'Realizado por',
      render: (item) =>
        item.realizado_por?.nombre_completo
        ?? item.realizado_por?.usuario_ti
        ?? '—',
    },
  ]

  return (
    <Stack gap="md" p="md">
      <OdontogramaChart
        piezas={odontograma.piezas}
        mostrarTemporal={mostrarTemporal}
        onSeleccionarPieza={handleSeleccionarPieza}
      />

      <Divider label="Historial reciente" labelPosition="left" />

      {historial.length === 0 ? (
        <EmptyState
          icon={IconDental}
          title="Sin procedimientos registrados"
          description="Haz clic en una pieza del odontograma para registrar un procedimiento."
        />
      ) : (
        <SgthTable
          records={historial}
          columns={columns}
          fetching={false}
          minHeight={100}
          rowExpansion={{
            content: ({ record }) => (
              <HistorialDetalle
                item={record}
                puedeAnular={esAnulable(record)}
                onAnular={() => handleAnular(record)}
              />
            ),
          }}
        />
      )}

      <RegistrarProcedimientoModal
        opened={modalOpened}
        onClose={() => { setPiezaSeleccionada(null); closeModal() }}
        pieza={piezaSeleccionada}
        historiaClinicaId={historiaClinicaId}
        consultaMedicaId={consultaMedicaId}
      />

      <AnularProcedimientoModal
        opened={anularModalOpened}
        onClose={() => { setItemParaAnular(null); closeAnularModal() }}
        item={itemParaAnular}
        numeroPieza={itemParaAnular?.numero_pieza}
        historiaClinicaId={historiaClinicaId}
        consultaMedicaId={consultaMedicaId}
      />
    </Stack>
  )
}
