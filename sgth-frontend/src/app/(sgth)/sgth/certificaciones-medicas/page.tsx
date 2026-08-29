'use client'

import { useState } from 'react'
import { Stack, Group, Badge, Text, Card, Select, ThemeIcon, Skeleton } from '@mantine/core'
import { IconClipboardHeart } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useSolicitudesCertificacion } from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import {
  TIPO_EVENTO_OPTIONS,
  ESTADO_SOLICITUD_COLORS,
  ESTADO_SOLICITUD_LABELS,
} from '@/features/dispensario/services/solicitudCertificacionService'
import type { SolicitudCertificacion } from
  '@/features/dispensario/services/solicitudCertificacionService'
import type { UnidadConRelaciones } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'
import { EmptyState, PageHeader, PageShell, SgthTable } from '@/components/ui'

const ANIO_ACTUAL = new Date().getFullYear()

function generarOpcionesAnio(): { value: string; label: string }[] {
  const opciones = [{ value: '', label: 'Todos los años' }]
  for (let anio = ANIO_ACTUAL; anio >= ANIO_ACTUAL - 5; anio--) {
    opciones.push({ value: String(anio), label: String(anio) })
  }
  return opciones
}

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

export default function CertificacionesMedicasPage() {
  const contained = useContainedInput()
  const [filtroEstado, setFiltroEstado] = useState<string>('')
  const [filtroUnidad, setFiltroUnidad] = useState<string>('')
  const [filtroAnio, setFiltroAnio] =
    useState<string>(String(ANIO_ACTUAL))

  const { data: unidades = [] } = useTodasUnidades()
  const unidadOptions = [
    { value: '', label: 'Todas las unidades' },
    ...((unidades ?? []) as UnidadConRelaciones[]).map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    })),
  ]

  const { data, isLoading } = useSolicitudesCertificacion({
    estado:   filtroEstado || undefined,
    unidad_administrativa_id: filtroUnidad ? Number(filtroUnidad) : undefined,
    anio:     filtroAnio ? Number(filtroAnio) : undefined,
    per_page: 20,
  })
  const solicitudes = data?.data ?? []

  const getLabelTipo = (tipo: string) =>
    TIPO_EVENTO_OPTIONS.find(o => o.value === tipo)?.label ?? tipo

  const getUnidadNombre = (s: SolicitudCertificacion) =>
    s.servidor?.unidad_administrativa?.nombre
      ?? s.convocatoria?.puesto?.unidad_administrativa?.nombre
      ?? null

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
      accessor: 'unidad',
      title:    'Unidad administrativa',
      width:    180,
      render: (s) => (
        <Text size="sm">{getUnidadNombre(s) ?? '—'}</Text>
      ),
    },
    {
      accessor: 'origen',
      title:    'Origen',
      width:    150,
      render: (s) => (
        <Stack gap={0}>
          <Text size="xs" c="dimmed" tt="capitalize">
            {s.origen === 'reclutamiento' ? 'Reclutamiento'
              : s.origen === 'expediente' ? 'Expediente'
              : 'Automático'}
          </Text>
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
  ]

  return (
    <PageShell>
      <PageHeader
        title="Certificaciones médicas"
        description="Seguimiento de solicitudes enviadas al dispensario médico"
      />

      <Card withBorder radius="lg" p="lg">
        <Stack gap="md">
          <Group gap="xs">
            <ThemeIcon
              color="blue" variant="light"
              size="md" radius="md"
            >
              <IconClipboardHeart size={16} />
            </ThemeIcon>
            <Stack gap={0}>
              <Text fw={600} size="sm">
                Solicitudes de certificación médica
              </Text>
              <Text size="xs" c="dimmed">
                Vista de solo lectura. La atención médica se
                gestiona desde el Dispensario.
              </Text>
            </Stack>
          </Group>

          <Group gap="sm" wrap="wrap">
            <Select
              label="Estado"
              placeholder="Filtrar por estado"
              data={[
                { value: '',           label: 'Todas'       },
                { value: 'pendiente',  label: 'Pendientes'  },
                { value: 'en_proceso', label: 'En proceso'  },
                { value: 'completada', label: 'Completadas' },
                { value: 'cancelada',  label: 'Canceladas'  },
              ]}
              style={{ width: 180 }}
              {...contained}
              value={filtroEstado}
              onChange={(v) => setFiltroEstado(v ?? '')}
            />
            <Select
              label="Unidad administrativa"
              placeholder="Todas las unidades"
              data={unidadOptions}
              searchable
              style={{ width: 240 }}
              {...contained}
              value={filtroUnidad}
              onChange={(v) => setFiltroUnidad(v ?? '')}
            />
            <Select
              label="Año"
              placeholder="Todos los años"
              data={generarOpcionesAnio()}
              style={{ width: 150 }}
              {...contained}
              value={filtroAnio}
              onChange={(v) => setFiltroAnio(v ?? '')}
            />
          </Group>

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
              description="No se han enviado solicitudes de certificación médica."
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
    </PageShell>
  )
}
