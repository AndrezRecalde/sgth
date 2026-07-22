'use client'

import { useState } from 'react'
import { Stack, Group, Text, Badge, Button, Skeleton } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconHistory, IconFileDownload } from '@tabler/icons-react'
import { notifications } from '@mantine/notifications'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { useMovimientos } from '../../hooks/useMovimientos'
import { MovimientoModal } from '../MovimientoModal'
import { expedienteService } from '../../services/expedienteService'
import { getApiErrorMessage } from '@/types/api'
import { ACCION_PERSONAL_LABELS } from '../../utils/nombramiento'
import type { MovimientoPersonal } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const TIPO_LABELS: Record<string, string> = {
  traslado: 'Traslado',
  ascenso: 'Ascenso',
  subrogacion: 'Subrogación',
  comision_servicios: 'Comisión de Servicios',
  cambio_regimen: 'Cambio de Régimen',
  cambio_puesto: 'Cambio de Puesto',
  ingreso: 'Ingreso',
  egreso: 'Egreso',
  novedad_contrato: 'Novedad de Contrato',
  ...ACCION_PERSONAL_LABELS,
}

function formatFecha(fecha?: string | null): string {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

interface Props {
  servidorId: number
  tipoNombramiento?: string | null
}

export function MovimientosTab({ servidorId, tipoNombramiento }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const { data: movimientos = [], isLoading } = useMovimientos(servidorId)
  const [descargandoId, setDescargandoId] = useState<number | null>(null)

  const lista = movimientos as MovimientoPersonal[]

  const handleDescargarPdf = async (movimiento: MovimientoPersonal) => {
    setDescargandoId(Number(movimiento.id))
    try {
      const blob = await expedienteService.descargarAccionPersonalPdf(Number(movimiento.id))
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `accion_personal_${movimiento.codigo ?? movimiento.id}.pdf`
      link.click()
      URL.revokeObjectURL(url)
    } catch (error) {
      notifications.show({
        title: 'Error',
        message: getApiErrorMessage(error, 'No se pudo generar el PDF de Acción de Personal.'),
        color: 'red',
      })
    } finally {
      setDescargandoId(null)
    }
  }

  const columns: DataTableColumn<MovimientoPersonal>[] = [
    {
      accessor: 'tipo_movimiento',
      title: 'Tipo',
      render: ({ tipo_movimiento }) => (
        <Text size="sm" fw={500}>
          {TIPO_LABELS[tipo_movimiento] ?? tipo_movimiento}
        </Text>
      ),
    },
    {
      accessor: 'descripcion',
      title: 'Descripción',
      render: ({ descripcion }) => (
        <Text size="sm" c="dimmed" lineClamp={2}>{descripcion}</Text>
      ),
    },
    {
      accessor: 'periodo',
      title: 'Período',
      width: 190,
      render: (m) => (
        <Text size="sm">
          {m.fecha_inicio
            ? `${formatFecha(m.fecha_inicio)} → ${formatFecha(m.fecha_fin)}`
            : formatFecha(m.fecha_efectiva)}
        </Text>
      ),
    },
    {
      accessor: 'autorizado_por_usuario',
      title: 'Autorizado por',
      render: (m) => (
        <Text size="sm" c="dimmed">
          {m.autorizado_por_usuario?.nombre_completo ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (m) => (
        <TableActions
          actions={[
            {
              label: 'Descargar PDF de Acción de Personal',
              icon: <IconFileDownload size={14} />,
              color: 'blue',
              disabled: descargandoId === Number(m.id),
              onClick: () => handleDescargarPdf(m),
            },
          ]}
        />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <Group justify="space-between" align="center">
        <Text size="sm" c="dimmed">
          Historial inmutable de movimientos y acciones de personal del servidor.
        </Text>
        <Button
          size="xs" color="emerald" variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={open}
        >
          Nueva acción de personal
        </Button>
      </Group>

      {!tipoNombramiento && (
        <Badge color="yellow" variant="light" size="sm">
          Sin contrato vigente — no se pueden registrar acciones de personal
        </Badge>
      )}

      {isLoading ? (
        <Skeleton height={120} radius="md" />
      ) : lista.length === 0 ? (
        <EmptyState
          icon={IconHistory}
          title="Sin movimientos registrados"
          description="El historial de movimientos y acciones de personal aparecerá aquí."
        />
      ) : (
        <SgthTable
          records={lista}
          columns={columns}
          fetching={false}
          minHeight={120}
        />
      )}

      <MovimientoModal
        opened={opened}
        onClose={close}
        servidorId={servidorId}
        tipoNombramiento={tipoNombramiento}
      />
    </Stack>
  )
}
