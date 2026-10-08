'use client'

import { useState } from 'react'
import { Group, Stack, Text } from '@mantine/core'
import { IconFingerprint, IconHeartbeat } from '@tabler/icons-react'
import {
  DataState, PAGINACION_ES, SectionHeading, SgthTable, StatusBadge,
} from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { useMisCertificados } from '../hooks/useMisPermisos'
import type { DataTableColumn } from 'mantine-datatable'
import type { MiCertificadoMedico } from '@/types/api'

const POR_PAGINA = 10

/** En qué quedó el reposo, con el mismo tono que en la viñeta de TH. */
function Estado({ c }: { c: MiCertificadoMedico }) {
  if (c.estado === 'anulado') return <StatusBadge tone="neutral">Anulado</StatusBadge>
  if (c.estado === 'pendiente') return <StatusBadge tone="warning">Por aprobar</StatusBadge>

  return (
    <Stack gap={2}>
      <StatusBadge tone="success">Aprobado</StatusBadge>
      <Group gap={4} wrap="nowrap" align="flex-start">
        <IconFingerprint size={12} color="var(--mantine-color-dimmed)" style={{ flexShrink: 0, marginTop: 2 }} />
        <Text size="xs" c="dimmed">
          {c.registro_sirha7 === 'manual' ? 'Cargado en Sirha7' : `En Sirha7: ${c.sirha7_leave_nombre}`}
        </Text>
      </Group>
    </Stack>
  )
}

const COLUMNAS: DataTableColumn<MiCertificadoMedico>[] = [
  {
    accessor: 'folio',
    title: 'Folio',
    width: 145,
    render: ({ folio }) => <Text size="sm" ff="monospace" fw={500}>{folio ?? '—'}</Text>,
  },
  {
    accessor: 'fecha_inicio',
    title: 'Reposo',
    render: (c) => (
      <Group gap="xs" wrap="nowrap">
        <Text size="sm">
          {formatFecha(c.fecha_inicio)}
          {c.dias_reposo > 1 && <Text span size="xs" c="dimmed"> al {formatFecha(c.fecha_fin)}</Text>}
        </Text>
        <StatusBadge size="xs">{c.dias_reposo === 1 ? '1 día' : `${c.dias_reposo} días`}</StatusBadge>
      </Group>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 200,
    render: (c) => <Estado c={c} />,
  },
]

/**
 * «Mis reposos médicos», dentro de «Mis permisos» del portal.
 *
 * Desde el 2026-10-08 el reposo del dispensario es un certificado que aprueba
 * Talento Humano o Trabajo Social, y no un permiso: aquí el servidor ve sus
 * días y si ya está en Sirha7, que es lo que justifica la ausencia en sus
 * marcaciones. Sin el diagnóstico. Se oculta si no tiene ninguno.
 */
export function MisRepososMedicos({ anio }: { anio?: number }) {
  const [page, setPage] = useState(1)
  const { data, isLoading, error } = useMisCertificados({ page, per_page: POR_PAGINA, anio })
  const lista = data?.data ?? []

  if (!isLoading && !error && !lista.length && page === 1) return null

  return (
    <Stack gap="sm" mt="lg">
      <SectionHeading title="Mis reposos médicos" />
      <DataState
        loading={isLoading}
        error={error}
        empty={!lista.length}
        emptyProps={{ icon: IconHeartbeat, title: 'Sin reposos médicos', description: 'No hay reposos en este período.' }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={lista}
          columns={COLUMNAS}
          totalRecords={data?.total ?? lista.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
        />
      </DataState>
    </Stack>
  )
}
