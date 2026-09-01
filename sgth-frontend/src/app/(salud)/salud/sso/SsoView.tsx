'use client'

import { useState } from 'react'
import { Stack, Badge, Text, Select } from '@mantine/core'
import {
  IconClipboardHeart,
  IconPlayerPlay,
  IconFileText,
  IconUserCheck,
  IconDownload,
} from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
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
  TONO_ESTADO_SOLICITUD,
  TONO_DICTAMEN,
  DICTAMEN_LABELS,
  ESTADO_SOLICITUD_LABELS,
} from '@/features/dispensario/services/solicitudCertificacionService'
import type { SolicitudCertificacion } from
  '@/features/dispensario/services/solicitudCertificacionService'
import type { DataTableColumn } from 'mantine-datatable'
import {
  DataState, PageHeader, PageShell, SgthTable, StatusBadge,
  TableActions, Toolbar, confirmar,
} from '@/components/ui'

export function SsoView() {
  const router    = useRouter()
  const contained = useContainedInput('sm')
  const { hasPermiso } = useAuth()
  const [filtroEstado, setFiltroEstado] =
    useState<string>('pendiente')

  const { data, isLoading, error } = useSolicitudesCertificacion({
    estado:   filtroEstado || undefined,
    per_page: 20,
  })
  const solicitudes = data?.data ?? []
  const iniciar     = useIniciarProceso()
  const incorporar  = useConfirmarIncorporacion()
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
          </Text>
        )
      },
    },
    {
      accessor: 'signos_vitales',
      title:    'Signos vitales',
      width:    150,
      render: (s) => (
        <StatusBadge tone={s.constantes_vitales ? 'success' : 'warning'}>
          {s.constantes_vitales ? 'Tomados' : 'Pendiente'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    160,
      render: (s) => (
        <Stack gap={4}>
          <StatusBadge tone={TONO_ESTADO_SOLICITUD[s.estado] ?? 'neutral'}>
            {ESTADO_SOLICITUD_LABELS[s.estado] ?? s.estado}
          </StatusBadge>
          {s.dictamen && (
            <StatusBadge size="xs" tone={TONO_DICTAMEN[s.dictamen] ?? 'neutral'}>
              {DICTAMEN_LABELS[s.dictamen] ?? s.dictamen}
            </StatusBadge>
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
            onClick: () => confirmar({
              title:   'Confirmar incorporación',
              message: (
                <>
                  Se creará el expediente de <b>{s.nombres_paciente}</b> como
                  servidor del GADPE. No se puede deshacer.
                </>
              ),
              confirmLabel: 'Confirmar incorporación',
              onConfirm: () => incorporar.mutate(s.id),
            }),
          }] : []),
        ]} />
      ),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Salud Ocupacional"
        description="Solo se pueden crear fichas FEMO a partir de una solicitud de Talento Humano"
        actions={
          pendientes > 0 ? (
            <StatusBadge tone="warning" size="md">
              {pendientes} pendiente{pendientes !== 1 ? 's' : ''}
            </StatusBadge>
          ) : undefined
        }
      />

      <Toolbar>
        <Select
          label="Estado"
          placeholder="Todas"
          data={[
            { value: '',           label: 'Todas'       },
            { value: 'pendiente',  label: 'Pendientes'  },
            { value: 'en_proceso', label: 'En proceso'  },
            { value: 'completada', label: 'Completadas' },
            { value: 'cancelada',  label: 'Canceladas'  },
          ]}
          style={{ minWidth: 200 }}
          {...contained}
          value={filtroEstado}
          onChange={(v) => setFiltroEstado(v ?? '')}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!solicitudes.length}
        emptyProps={{
          icon: IconClipboardHeart,
          title: 'Sin solicitudes',
          description: filtroEstado === 'pendiente'
            ? 'No hay solicitudes de certificación pendientes de Talento Humano.'
            : 'No hay solicitudes en este estado.',
        }}
      >
        <SgthTable
          records={solicitudes}
          columns={columns}
          minHeight={200}
        />
      </DataState>
    </PageShell>
  )
}
