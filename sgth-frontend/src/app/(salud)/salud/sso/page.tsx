'use client'

import { useState } from 'react'
import {
  Stack, Group, Badge, Text, Card,
  Select, ThemeIcon, Skeleton,
} from '@mantine/core'
import {
  IconShieldCheck, IconClipboardHeart,
  IconPlayerPlay,
  IconFileText, IconUserCheck,
  IconDownload,
} from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { PageHeader } from '@/components/ui/PageHeader'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuth } from '@/hooks/useAuth'
import {
  useSolicitudesCertificacion,
  useIniciarProceso,
  useConfirmarIncorporacion,
} from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { usePdfFemo } from '@/features/dispensario/hooks/usePdfFemo'
import {
  TIPO_EVENTO_OPTIONS,
  ESTADO_SOLICITUD_COLORS,
  ESTADO_SOLICITUD_LABELS,
} from '@/features/dispensario/services/solicitudCertificacionService'
import type { SolicitudCertificacion } from
  '@/features/dispensario/services/solicitudCertificacionService'
import type { DataTableColumn } from 'mantine-datatable'

const DICTAMEN_COLORS: Record<string, string> = {
  apto:                   'emerald',
  apto_con_restricciones: 'orange',
  no_apto:                'red',
}

const DICTAMEN_LABELS: Record<string, string> = {
  apto:                   'Apto',
  apto_con_restricciones: 'Apto c/restricciones',
  no_apto:                'No apto',
}

export default function SsoPage() {
  const router    = useRouter()
  const contained = useContainedInput()
  const { hasPermiso } = useAuth()
  const [filtroEstado, setFiltroEstado] =
    useState<string>('pendiente')

  const { data, isLoading } = useSolicitudesCertificacion({
    estado:   filtroEstado || undefined,
    per_page: 20,
  })
  const solicitudes = data?.data ?? []
  const iniciar     = useIniciarProceso()
  const confirmar   = useConfirmarIncorporacion()
  const { descargarFemo, loading: descargando } = usePdfFemo()
  const puedeConfirmarIncorporacion = hasPermiso('gestionar-onboarding')

  const pendientes = solicitudes.filter(
    s => s.estado === 'pendiente'
  ).length

  const getLabelTipo = (tipo: string) =>
    TIPO_EVENTO_OPTIONS.find(o => o.value === tipo)?.label ?? tipo

  const columns: DataTableColumn<SolicitudCertificacion>[] = [
    {
      accessor: 'tipo_evento',
      title:    'Tipo de evaluación',
      width:    180,
      render: (s) => (
        <Badge size="sm" variant="light" color="blue">
          {getLabelTipo(s.tipo_evento)}
        </Badge>
      ),
    },
    {
      accessor: 'paciente',
      title:    'Servidor / Candidato',
      render: (s) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{s.nombres_paciente}</Text>
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
      accessor: 'origen',
      title:    'Solicitado por',
      width:    160,
      render: (s) => (
        <Stack gap={0}>
          <Text size="xs" c="dimmed" tt="capitalize">
            {s.origen === 'reclutamiento' ? 'Reclutamiento'
              : s.origen === 'expediente' ? 'Expediente'
              : 'Automático'}
          </Text>
          {s.convocatoria && (
            <Text size="xs" ff="monospace" c="dimmed">
              {s.convocatoria.codigo}
            </Text>
          )}
          {s.solicitado_por?.servidor && (
            <Text size="xs" c="dimmed">
              {s.solicitado_por.servidor.nombre}{' '}
              {s.solicitado_por.servidor.apellido}
            </Text>
          )}
        </Stack>
      ),
    },
    {
      accessor: 'fecha_limite',
      title:    'Fecha límite',
      width:    110,
      render: (s) => {
        if (!s.fecha_limite) return <Text size="sm">—</Text>
        const fecha   = new Date(s.fecha_limite)
        const hoy     = new Date()
        const urgente = fecha <= hoy && s.estado !== 'completada'
        return (
          <Text
            size="sm"
            c={urgente ? 'red' : undefined}
            fw={urgente ? 600 : undefined}
          >
            {fecha.toLocaleDateString('es-EC', {
              day: '2-digit', month: 'short', year: 'numeric',
            })}
            {urgente && ' ⚠️'}
          </Text>
        )
      },
    },
    {
      accessor: 'signos_vitales',
      title:    'Signos vitales',
      width:    150,
      render: (s) => (
        <Badge
          size="sm"
          variant="light"
          color={s.constantes_vitales ? 'emerald' : 'orange'}
        >
          {s.constantes_vitales ? 'Tomados' : 'Pendiente'}
        </Badge>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    160,
      render: (s) => (
        <Stack gap={4}>
          <Badge
            size="sm"
            variant="light"
            color={ESTADO_SOLICITUD_COLORS[s.estado] ?? 'gray'}
          >
            {ESTADO_SOLICITUD_LABELS[s.estado] ?? s.estado}
          </Badge>
          {s.dictamen && (
            <Badge
              size="xs"
              variant="dot"
              color={DICTAMEN_COLORS[s.dictamen] ?? 'gray'}
            >
              {DICTAMEN_LABELS[s.dictamen] ?? s.dictamen}
            </Badge>
          )}
        </Stack>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (s) => (
        <TableActions actions={[
          ...(s.estado === 'pendiente' ? [{
            label:   s.constantes_vitales
              ? 'Iniciar y crear FEMO'
              : 'Pendiente signos vitales (Enfermería)',
            icon:    <IconPlayerPlay size={14} />,
            color:   'blue',
            disabled: !s.constantes_vitales,
            onClick: () => {
              iniciar.mutate(s.id, {
                onSuccess: () =>
                  router.push(`/salud/sso/femo/nueva/${s.id}`),
              })
            },
          }] : []),
          ...(s.estado === 'en_proceso' ? [{
            label:   s.constantes_vitales
              ? 'Continuar FEMO'
              : 'Pendiente signos vitales (Enfermería)',
            icon:    <IconFileText size={14} />,
            color:   'blue',
            disabled: !s.constantes_vitales,
            onClick: () =>
              router.push(`/salud/sso/femo/nueva/${s.id}`),
          }] : []),
          ...(s.ficha_femo_id ? [{
            label:   'Descargar PDF de la ficha FEMO',
            icon:    <IconDownload size={14} />,
            color:   'blue',
            disabled: descargando,
            onClick: () => descargarFemo(
              s.ficha_femo_id!,
              `femo-${s.cedula_paciente}-${s.id}.pdf`
            ),
          }] : []),
          ...(s.estado === 'completada' &&
            !!s.postulante && !s.servidor &&
            puedeConfirmarIncorporacion &&
            (s.dictamen === 'apto' ||
             s.dictamen === 'apto_con_restricciones') ? [{
            label:   'Confirmar incorporación',
            icon:    <IconUserCheck size={14} />,
            color:   'emerald',
            onClick: () => {
              if (confirm(
                `¿Confirmar la incorporación de ${s.nombres_paciente}?\n\n` +
                `El sistema creará su expediente como servidor del GADPE.\n` +
                `Esta acción no se puede deshacer.`
              )) {
                confirmar.mutate(s.id)
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
        title="Salud Ocupacional"
        subtitle="Solicitudes de certificación médica de Talento Humano"
        icon={<IconShieldCheck size={24} />}
      />

      <Card withBorder radius="lg" p="lg">
        <Stack gap="md">
          <Group justify="space-between">
            <Group gap="xs">
              <ThemeIcon
                color="blue" variant="light"
                size="md" radius="md"
              >
                <IconClipboardHeart size={16} />
              </ThemeIcon>
              <Stack gap={0}>
                <Text fw={600} size="sm">
                  Solicitudes de Talento Humano
                </Text>
                <Text size="xs" c="dimmed">
                  Solo se pueden crear fichas FEMO a partir
                  de una solicitud de RRHH
                </Text>
              </Stack>
            </Group>
            {pendientes > 0 && (
              <Badge size="md" variant="light" color="orange">
                {pendientes} pendiente{pendientes !== 1 ? 's' : ''}
              </Badge>
            )}
          </Group>

          <Select
            placeholder="Filtrar por estado"
            data={[
              { value: '',           label: 'Todas'       },
              { value: 'pendiente',  label: 'Pendientes'  },
              { value: 'en_proceso', label: 'En proceso'  },
              { value: 'completada', label: 'Completadas' },
              { value: 'cancelada',  label: 'Canceladas'  },
            ]}
            style={{ width: 200 }}
            {...contained}
            value={filtroEstado}
            onChange={(v) => setFiltroEstado(v ?? '')}
          />

          {isLoading ? (
            <Stack gap="xs">
              <Skeleton height={64} radius="md" />
              <Skeleton height={64} radius="md" />
              <Skeleton height={64} radius="md" />
            </Stack>
          ) : solicitudes.length === 0 ? (
            <EmptyState
              icon={IconClipboardHeart}
              title="Sin solicitudes"
              description={
                filtroEstado === 'pendiente'
                  ? 'No hay solicitudes de certificación pendientes de Talento Humano.'
                  : 'No hay solicitudes en este estado.'
              }
            />
          ) : (
            <SgthTable
              records={solicitudes}
              columns={columns}
              fetching={isLoading}
              minHeight={200}
            />
          )}
        </Stack>
      </Card>
    </Stack>
  )
}
