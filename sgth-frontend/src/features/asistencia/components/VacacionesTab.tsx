'use client'

import { useState } from 'react'

import { Stack, Group, Button, Text, Badge, Chip, ActionIcon, Tooltip } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconBeach, IconCheck, IconX, IconPrinter } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { VacacionModal } from './VacacionModal'
import { useVacaciones } from '../hooks/useVacaciones'
import { useVacacionMutations } from '../hooks/useVacacionMutations'
import { notifications } from '@mantine/notifications'
import { asistenciaService } from '../services/asistenciaService'
import type { Vacacion, EstadoVacacion, MotivoVacacion } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const ESTADO_COLORS: Record<EstadoVacacion, string> = {
  pendiente: 'orange',
  aprobada:  'emerald',
  rechazada: 'red',
  gozada:    'gray',
}

const ESTADO_LABELS: Record<EstadoVacacion, string> = {
  pendiente: 'Pendiente',
  aprobada:  'Aprobada',
  rechazada: 'Rechazada',
  gozada:    'Gozada',
}

const MOTIVO_LABELS: Record<MotivoVacacion, string> = {
  vacaciones_anuales:        'Vacaciones Anuales',
  permiso_cargo_vacaciones:  'Cargo a Vacaciones',
  licencia_sin_goce:         'Licencia sin Goce',
  matrimonio:                'Matrimonio',
  capacitacion:              'Capacitación',
  enfermedad:                'Enfermedad',
  maternidad:                'Maternidad',
  paternidad:                'Paternidad',
  estudios_sin_remuneracion: 'Estudios sin Rem.',
  calamidad_domestica:       'Calamidad',
  licencia_con_goce:         'Licencia con Goce',
}

export function VacacionesTab() {
  const [opened, { open, close }] = useDisclosure(false)
  const { data, isLoading } = useVacaciones()
  const { actualizar } = useVacacionMutations()

  const [exportandoId, setExportandoId] = useState<number | null>(null)
  const [filtroEstado, setFiltroEstado] = useState<string>('pendiente')

  const handleExportar = async (id: number) => {
    setExportandoId(id)
    try {
      const blob = await asistenciaService.vacaciones.exportar(id)
      const url  = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href  = url
      link.download = `vacacion_${id}.pdf`
      link.click()
      URL.revokeObjectURL(url)
      notifications.show({
        title:   'PDF descargado',
        message: 'La solicitud fue exportada.',
        color:   'emerald',
      })
    } catch {
      notifications.show({
        title:   'Error',
        message: 'No se pudo exportar.',
        color:   'red',
      })
    } finally {
      setExportandoId(null)
    }
  }

  const lista = (
    Array.isArray(data)
      ? data
      : (data as { data?: Vacacion[] } | null)?.data ?? []
  ).filter((v: Vacacion) =>
    filtroEstado === 'todos'
      ? true
      : (v.estado as string) === filtroEstado
  ) as Vacacion[]

  const columns: DataTableColumn<Vacacion>[] = [
    {
      accessor: 'folio',
      title:    'Folio',
      width:    140,
      render: ({ folio }) => (
        <Text size="sm" ff="monospace" fw={500}>
          {folio ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'servidor',
      title:    'Servidor',
      render: (v) => {
        const s = v.servidor
        if (!s) return <Text size="sm" c="dimmed">—</Text>
        return (
          <Text size="sm">
            {[s.apellido, s.nombre].filter(Boolean).join(' ')}
          </Text>
        )
      },
    },
    {
      accessor: 'unidad_administrativa',
      title:    'Unidad',
      render: (v) => {
        const nombre = (v.unidad_administrativa as {
          nombre?: string
        } | null)?.nombre
        return (
          <Text size="sm" c="dimmed">
            {nombre ?? '—'}
          </Text>
        )
      },
    },
    {
      accessor: 'motivo',
      title:    'Motivo',
      render: ({ motivo }) => (
        <Text size="sm">
          {MOTIVO_LABELS[motivo] ?? motivo}
        </Text>
      ),
    },
    {
      accessor: 'fecha_inicio',
      title:    'Desde',
      width:    110,
      render: ({ fecha_inicio }) => (
        <Text size="sm">
          {fecha_inicio
            ? new Date(fecha_inicio).toLocaleDateString('es-EC', {
                timeZone: 'UTC',
                day: '2-digit', month: '2-digit', year: 'numeric',
              })
            : '—'}
        </Text>
      ),
    },
    {
      accessor: 'fecha_fin',
      title:    'Fecha fin',
      width:    110,
      render: ({ fecha_fin }) => (
        <Text size="sm">
          {fecha_fin
            ? new Date(fecha_fin).toLocaleDateString('es-EC', {
                timeZone: 'UTC',
                day: '2-digit', month: '2-digit', year: 'numeric',
              })
            : '—'}
        </Text>
      ),
    },
    {
      accessor: 'dias_solicitados',
      title:    'Días',
      width:    70,
      render: ({ dias_solicitados }) => (
        <Text size="sm" ta="center">{dias_solicitados}</Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    110,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado] ?? 'gray'}
          variant="light" size="sm"
        >
          {ESTADO_LABELS[estado] ?? estado}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (v) => (
        <Group gap={4} justify="center">
          <Tooltip label="Imprimir solicitud">
            <ActionIcon
              size="sm"
              variant="light"
              color="blue"
              loading={exportandoId === v.id}
              onClick={() => handleExportar(v.id)}
            >
              <IconPrinter size={14} />
            </ActionIcon>
          </Tooltip>
          <TableActions actions={[
          {
            label:    'Aprobar',
            icon:     <IconCheck size={14} />,
            color:    'emerald',
            onClick:  () => actualizar.mutate({
              id: v.id, data: { estado: 'aprobada' },
            }),
            hidden: v.estado !== 'pendiente',
          },
          {
            label:    'Rechazar',
            icon:     <IconX size={14} />,
            color:    'red',
            onClick:  () => {
              if (confirm('¿Rechazar esta solicitud?'))
                actualizar.mutate({
                  id: v.id, data: { estado: 'rechazada' },
                })
            },
            hidden: v.estado !== 'pendiente',
          },
        ]} />
        </Group>
      ),
    },
  ]

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button
          size="xs" color="emerald" variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={open}
        >
          Nueva solicitud
        </Button>
      </Group>

      <Group gap="xs" mb="sm">
        <Text size="sm" fw={500} c="dimmed">Estado:</Text>
        {[
          { value: 'todos',     label: 'Todos',     color: 'gray'    },
          { value: 'pendiente', label: 'Pendiente', color: 'orange'  },
          { value: 'aprobada',  label: 'Aprobada',  color: 'emerald' },
          { value: 'rechazada', label: 'Rechazada', color: 'red'     },
          { value: 'gozada',    label: 'Gozada',    color: 'gray'    },
        ].map(op => (
          <Chip
            key={op.value}
            size="sm"
            color={op.color}
            checked={filtroEstado === op.value}
            onChange={() => setFiltroEstado(op.value)}
          >
            {op.label}
          </Chip>
        ))}
      </Group>

      {lista.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconBeach}
          title="Sin solicitudes de vacaciones"
        />
      ) : (
        <SgthTable
          records={lista}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      )}

      <VacacionModal opened={opened} onClose={close} />
    </Stack>
  )
}
