'use client'

import { useState } from 'react'
import {
  Stack, Badge, Text, Button,
} from '@mantine/core'
import {
  IconSpeakerphone, IconPlus,
  IconEye, IconEdit, IconTrash,
  IconWorldUpload,
} from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { PageHeader } from '@/components/ui/PageHeader'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import {
  useConvocatorias,
  useEliminarConvocatoria,
  usePublicarConvocatoria,
} from '@/features/seleccion/hooks/useConvocatoria'
import {
  ESTADO_CONVOCATORIA_COLORS,
  ESTADO_CONVOCATORIA_OPTIONS,
  TIPO_CONVOCATORIA_OPTIONS,
} from '@/features/seleccion/services/convocatoriaService'
import type { Convocatoria } from
  '@/features/seleccion/services/convocatoriaService'
import type { DataTableColumn } from 'mantine-datatable'

export default function ConvocatoriasPage() {
  const router   = useRouter()
  const [page, setPage] = useState(1)
  const eliminar = useEliminarConvocatoria()
  const publicar = usePublicarConvocatoria()

  const { data, isLoading } = useConvocatorias({
    page, per_page: 15,
  })
  const convocatorias = data?.data ?? []

  const getLabelEstado = (v: string) =>
    ESTADO_CONVOCATORIA_OPTIONS.find(o => o.value === v)?.label ?? v

  const getLabelTipo = (v: string) =>
    TIPO_CONVOCATORIA_OPTIONS.find(o => o.value === v)?.label ?? v

  const columns: DataTableColumn<Convocatoria>[] = [
    {
      accessor: 'codigo',
      title:    'Código',
      width:    170,
      render: (c) => (
        <Text size="sm" ff="monospace">{c.codigo}</Text>
      ),
    },
    {
      accessor: 'titulo',
      title:    'Convocatoria',
      render: (c) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{c.titulo}</Text>
          <Text size="xs" c="dimmed">
            {c.puesto?.cargo?.nombre ?? '—'}
            {c.puesto?.unidad_administrativa?.nombre
              ? ` · ${c.puesto.unidad_administrativa.nombre}`
              : ''}
          </Text>
        </Stack>
      ),
    },
    {
      accessor: 'tipo',
      title:    'Tipo',
      width:    100,
      render: (c) => (
        <Badge size="sm" variant="light" color="blue">
          {getLabelTipo(c.tipo)}
        </Badge>
      ),
    },
    {
      accessor: 'vacantes',
      title:    'Vacantes',
      width:    80,
      render: (c) => (
        <Text size="sm" ta="center">{c.vacantes}</Text>
      ),
    },
    {
      accessor: 'fecha_inicio',
      title:    'Período',
      width:    160,
      render: (c) => (
        <Text size="xs">
          {new Date(c.fecha_inicio).toLocaleDateString('es-EC', {
            day: '2-digit', month: 'short', year: 'numeric',
          })}
          {' — '}
          {new Date(c.fecha_fin).toLocaleDateString('es-EC', {
            day: '2-digit', month: 'short', year: 'numeric',
          })}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    120,
      render: (c) => (
        <Badge
          size="sm"
          variant="light"
          color={ESTADO_CONVOCATORIA_COLORS[c.estado] ?? 'gray'}
        >
          {getLabelEstado(c.estado)}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (c) => (
        <TableActions actions={[
          {
            label:   'Ver detalle',
            icon:    <IconEye size={14} />,
            color:   'blue',
            onClick: () => router.push(
              `/sgth/reclutamiento/convocatorias/${c.id}`
            ),
          },
          ...(c.estado === 'borrador' ? [{
            label:   'Publicar',
            icon:    <IconWorldUpload size={14} />,
            color:   'emerald',
            onClick: () => {
              if (confirm('¿Publicar esta convocatoria?')) {
                publicar.mutate(c.id)
              }
            },
          }] : []),
          {
            label:   'Editar',
            icon:    <IconEdit size={14} />,
            color:   'blue',
            onClick: () => router.push(
              `/sgth/reclutamiento/convocatorias/${c.id}/editar`
            ),
          },
          ...(c.estado === 'borrador' ? [{
            label:   'Eliminar',
            icon:    <IconTrash size={14} />,
            color:   'red',
            onClick: () => {
              if (confirm(`¿Eliminar "${c.titulo}"?`)) {
                eliminar.mutate(c.id)
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
        title="Convocatorias"
        subtitle="Gestión de procesos de selección e incorporación"
        icon={<IconSpeakerphone size={24} />}
        actions={
          <Button
            color="emerald"
            leftSection={<IconPlus size={14} />}
            onClick={() =>
              router.push('/sgth/reclutamiento/convocatorias/nueva')
            }
          >
            Nueva convocatoria
          </Button>
        }
      />

      {convocatorias.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconSpeakerphone}
          title="Sin convocatorias"
          description="No hay convocatorias registradas aún."
        />
      ) : (
        <SgthTable
          records={convocatorias}
          columns={columns}
          fetching={isLoading}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      )}
    </Stack>
  )
}
