'use client'

import { useState } from 'react'
import {
  Stack, Group, Badge, Text, Card,
  Button, Grid, ThemeIcon, Select,
  Skeleton, Alert,
} from '@mantine/core'
import {
  IconHeartbeat, IconClipboardHeart,
  IconCheck, IconPlayerPlay,
  IconAlertCircle,
} from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { DisponibilidadToggle } from
  '@/features/dispensario/components/DisponibilidadToggle'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useSolicitudesCertificacion,
  useIniciarProceso,
  useCompletarSolicitud,
} from '@/features/dispensario/hooks/useSolicitudCertificacion'
import {
  TIPO_EVENTO_OPTIONS,
  ESTADO_SOLICITUD_COLORS,
  ESTADO_SOLICITUD_LABELS,
} from '@/features/dispensario/services/solicitudCertificacionService'
import type { SolicitudCertificacion } from
  '@/features/dispensario/services/solicitudCertificacionService'
import type { DataTableColumn } from 'mantine-datatable'

export default function SaludHomePage() {
  const { usuario } = useAuth()
  const contained   = useContainedInput()
  const roles = (usuario?.roles as string[]) ?? []
  const esPersonalClinico = roles.some(r =>
    ['medico', 'odontologo', 'enfermera',
     'admin-dispensario'].includes(r)
  )
  const [filtroEstado, setFiltroEstado] = useState<string>('pendiente')

  const { data, isLoading } = useSolicitudesCertificacion({
    estado:   filtroEstado || undefined,
    per_page: 10,
  })
  const solicitudes = data?.data ?? []
  const iniciar    = useIniciarProceso()
  const completar  = useCompletarSolicitud()

  const pendientes  = solicitudes.filter(s => s.estado === 'pendiente').length
  const enProceso   = solicitudes.filter(s => s.estado === 'en_proceso').length

  const getNombrePaciente = (s: SolicitudCertificacion) =>
    s.nombres_paciente || '—'

  const getLabelTipo = (tipo: string) =>
    TIPO_EVENTO_OPTIONS.find(o => o.value === tipo)?.label ?? tipo

  const columns: DataTableColumn<SolicitudCertificacion>[] = [
    {
      accessor: 'tipo_evento',
      title:    'Tipo',
      width:    150,
      render: (s) => (
        <Badge size="sm" variant="light" color="blue">
          {getLabelTipo(s.tipo_evento)}
        </Badge>
      ),
    },
    {
      accessor: 'paciente',
      title:    'Paciente',
      render: (s) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>
            {getNombrePaciente(s)}
          </Text>
          <Text size="xs" c="dimmed" ff="monospace">
            {s.cedula_paciente}
          </Text>
          {s.puesto_solicitado && (
            <Text size="xs" c="dimmed">
              {s.puesto_solicitado}
            </Text>
          )}
        </Stack>
      ),
    },
    {
      accessor: 'convocatoria',
      title:    'Origen',
      width:    140,
      render: (s) => (
        <Stack gap={0}>
          <Text size="xs" c="dimmed" tt="capitalize">
            {s.origen}
          </Text>
          {s.convocatoria && (
            <Text size="xs" ff="monospace">
              {s.convocatoria.codigo}
            </Text>
          )}
        </Stack>
      ),
    },
    {
      accessor: 'fecha_limite',
      title:    'Fecha límite',
      width:    110,
      render: (s) => (
        <Text size="sm">
          {s.fecha_limite
            ? new Date(s.fecha_limite).toLocaleDateString('es-EC', {
                day: '2-digit', month: 'short', year: 'numeric',
              })
            : '—'}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    120,
      render: (s) => (
        <Badge
          size="sm"
          variant="light"
          color={ESTADO_SOLICITUD_COLORS[s.estado] ?? 'gray'}
        >
          {ESTADO_SOLICITUD_LABELS[s.estado] ?? s.estado}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (s) => (
        <TableActions actions={[
          ...(s.estado === 'pendiente' ? [{
            label:   'Iniciar proceso',
            icon:    <IconPlayerPlay size={14} />,
            color:   'blue',
            onClick: () => iniciar.mutate(s.id),
          }] : []),
          ...(s.estado === 'en_proceso' ? [{
            label:   'Marcar completada',
            icon:    <IconCheck size={14} />,
            color:   'emerald',
            onClick: () => {
              if (confirm(
                '¿Marcar esta solicitud como completada?\n' +
                'Asegúrese de haber emitido el certificado médico.'
              )) {
                completar.mutate({ id: s.id })
              }
            },
          }] : []),
        ]} />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <PageHeader
        title="Dispensario Médico"
        subtitle="Sistema de Salud Ambulatoria — GADPE"
        icon={<IconHeartbeat size={24} />}
      />

      {esPersonalClinico && <DisponibilidadToggle />}

      <Card withBorder radius="lg" p="lg">
        <Stack gap="md">
          <Group justify="space-between">
            <Group gap="xs">
              <ThemeIcon color="blue" variant="light" size="md" radius="md">
                <IconClipboardHeart size={16} />
              </ThemeIcon>
              <Text fw={600}>
                Solicitudes de certificación médica
              </Text>
            </Group>
            <Group gap="xs">
              {pendientes > 0 && (
                <Badge size="sm" variant="light" color="orange">
                  {pendientes} pendiente{pendientes !== 1 ? 's' : ''}
                </Badge>
              )}
              {enProceso > 0 && (
                <Badge size="sm" variant="light" color="blue">
                  {enProceso} en proceso
                </Badge>
              )}
            </Group>
          </Group>

          <Group gap="sm">
            <Select
              placeholder="Filtrar por estado"
              data={[
                { value: '',           label: 'Todas'       },
                { value: 'pendiente',  label: 'Pendientes'  },
                { value: 'en_proceso', label: 'En proceso'  },
                { value: 'completada', label: 'Completadas' },
                { value: 'cancelada',  label: 'Canceladas'  },
              ]}
              clearable
              style={{ width: 180 }}
              {...contained}
              value={filtroEstado}
              onChange={(v) => setFiltroEstado(v ?? 'pendiente')}
            />
          </Group>

          {isLoading ? (
            <Stack gap="xs">
              <Skeleton height={60} radius="md" />
              <Skeleton height={60} radius="md" />
              <Skeleton height={60} radius="md" />
            </Stack>
          ) : solicitudes.length === 0 ? (
            <EmptyState
              icon={IconClipboardHeart}
              title="Sin solicitudes"
              description={
                filtroEstado === 'pendiente'
                  ? 'No hay solicitudes de certificación pendientes.'
                  : 'No hay solicitudes en este estado.'
              }
            />
          ) : (
            <SgthTable
              records={solicitudes}
              columns={columns}
              fetching={isLoading}
              minHeight={150}
            />
          )}
        </Stack>
      </Card>
    </Stack>
  )
}
