'use client'

import { Stack, Badge, Text } from '@mantine/core'
import {
  IconClipboardHeart, IconEye,
} from '@tabler/icons-react'

import { useRouter } from 'next/navigation'
import { useFemos } from
  '@/features/dispensario/hooks/useFemo'
import {
  TIPO_FICHA_OPTIONS,
  APTITUD_OPTIONS,
  APTITUD_COLORS,
} from '@/features/dispensario/services/femoService'
import type { FichaSaludOcupacional } from
  '@/features/dispensario/services/femoService'
import type { DataTableColumn } from 'mantine-datatable'
import { EmptyState, PageHeader, PageShell, SgthTable, TableActions } from '@/components/ui'

export default function FemoPage() {
  const router = useRouter()
  const { data, isLoading } = useFemos()
  const fichas = data?.data ?? []

  const getLabelTipo = (v: string) =>
    TIPO_FICHA_OPTIONS.find(o => o.value === v)?.label ?? v

  const getLabelAptitud = (v: string) =>
    APTITUD_OPTIONS.find(o => o.value === v)?.label ?? v

  const columns: DataTableColumn<FichaSaludOcupacional>[] = [
    {
      accessor: 'fecha_evaluacion',
      title:    'Fecha',
      width:    120,
      render: (f) => (
        <Text size="sm">
          {new Date(f.fecha_evaluacion).toLocaleDateString('es-EC', {
            day: '2-digit', month: 'short', year: 'numeric',
          })}
        </Text>
      ),
    },
    {
      accessor: 'servidor',
      title:    'Servidor / Aspirante',
      render: (f) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>
            {f.servidor
              ? `${f.servidor.nombre} ${f.servidor.apellido}`
              : f.postulante
                ? `${f.postulante.nombres} ${f.postulante.apellidos}`
                : '—'}
          </Text>
          <Text size="xs" c="dimmed" ff="monospace">
            {f.servidor?.cedula ?? f.postulante?.cedula ?? ''}
          </Text>
        </Stack>
      ),
    },
    {
      accessor: 'puesto_trabajo',
      title:    'Puesto',
      render: (f) => (
        <Text size="sm">{f.puesto_trabajo ?? '—'}</Text>
      ),
    },
    {
      accessor: 'tipo_ficha',
      title:    'Tipo',
      width:    150,
      render: (f) => (
        <Badge size="sm" variant="light" color="blue">
          {getLabelTipo(f.tipo_ficha)}
        </Badge>
      ),
    },
    {
      accessor: 'aptitud',
      title:    'Aptitud',
      width:    160,
      render: (f) => (
        <Badge
          size="sm"
          variant="light"
          color={APTITUD_COLORS[f.aptitud] ?? 'gray'}
        >
          {getLabelAptitud(f.aptitud)}
        </Badge>
      ),
    },
    {
      accessor: 'evaluador',
      title:    'Evaluador',
      width:    160,
      render: (f) => {
        const ev = f.evaluador?.servidor
        return (
          <Text size="sm">
            {ev ? `Dr. ${ev.nombre} ${ev.apellido}` : '—'}
          </Text>
        )
      },
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (f) => (
        <TableActions actions={[
          {
            label:   'Ver detalle',
            icon:    <IconEye size={14} />,
            color:   'blue',
            onClick: () => router.push(
              `/salud/sso/femo/${f.id}`
            ),
          },
        ]} />
      ),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Fichas de Salud Ocupacional"
        description="FEMO — Evaluaciones médicas ocupacionales"
      />

      {fichas.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconClipboardHeart}
          title="Sin fichas registradas"
          description="No hay fichas FEMO registradas aún."
        />
      ) : (
        <SgthTable
          records={fichas}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      )}
    </PageShell>
  )
}
