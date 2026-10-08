'use client'

import { Group, Stack, Text } from '@mantine/core'
import { IconFingerprint } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFecha, formatFechaHora } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'
import type { CertificadoAprobacion } from '@/types/api'

interface Acciones {
  /** Ni el propio ni uno ya aprobado o anulado: el backend lo rechaza. */
  puedeAprobar: (c: CertificadoAprobacion) => boolean
  onAprobar:    (c: CertificadoAprobacion) => void
}

/** En qué quedó: pendiente, aprobado (en Sirha7 o a mano) o anulado. */
function EstadoCertificado({ c }: { c: CertificadoAprobacion }) {
  if (c.anulado_en) return <StatusBadge tone="neutral">Anulado</StatusBadge>
  if (!c.aprobado_en) return <StatusBadge tone="warning">Pendiente</StatusBadge>

  return (
    <Stack gap={2}>
      <StatusBadge tone="success">Aprobado</StatusBadge>
      <Group gap={4} wrap="nowrap" align="flex-start">
        <IconFingerprint size={12} color="var(--mantine-color-dimmed)" style={{ flexShrink: 0, marginTop: 2 }} />
        <Text size="xs" c="dimmed">
          {c.registro_sirha7 === 'manual' ? 'Cargado a mano en Sirha7' : `En Sirha7: ${c.sirha7_leave_nombre}`}
        </Text>
      </Group>
    </Stack>
  )
}

export function getCertificadosColumns({ puedeAprobar, onAprobar }: Acciones): DataTableColumn<CertificadoAprobacion>[] {
  return [
    {
      accessor: 'folio',
      title: 'Folio',
      width: 145,
      render: ({ folio }) => <Text size="sm" ff="monospace" fw={500}>{folio ?? '—'}</Text>,
    },
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: ({ servidor }) => servidor ? (
        <Stack gap={0}>
          <Text size="sm">{[servidor.apellido, servidor.nombre].filter(Boolean).join(' ')}</Text>
          {servidor.unidad && <Text size="xs" c="dimmed" lineClamp={1}>{servidor.unidad}</Text>}
        </Stack>
      ) : <Text size="sm" c="dimmed">—</Text>,
    },
    {
      accessor: 'fecha_inicio',
      title: 'Reposo',
      width: 150,
      render: (c) => (
        <Stack gap={2}>
          <Text size="sm">
            {formatFecha(c.fecha_inicio)}
            {c.dias_reposo > 1 && <Text span size="xs" c="dimmed"> al {formatFecha(c.fecha_fin)}</Text>}
          </Text>
          <StatusBadge size="xs">{c.dias_reposo === 1 ? '1 día' : `${c.dias_reposo} días`}</StatusBadge>
        </Stack>
      ),
    },
    {
      accessor: 'emitido_en',
      title: 'Emitido',
      // «01/10/2026, 10:00 a. m.» partía la hora en dos líneas con 170.
      width: 200,
      render: (c) => (
        <Stack gap={0}>
          <Text size="sm">{formatFechaHora(c.emitido_en)}</Text>
          {c.medico && <Text size="xs" c="dimmed" lineClamp={1}>{c.medico}</Text>}
        </Stack>
      ),
    },
    {
      accessor: 'aprobado_en',
      title: 'Estado',
      // «DISPENSARIO MÉDICO GADPE» pasa a la línea siguiente, como en Permisos.
      width: 190,
      render: (c) => <EstadoCertificado c={c} />,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (c) => (
        <TableActions
          actions={[
            {
              label: 'Aprobar',
              icon: <IconFingerprint size={14} />,
              onClick: () => onAprobar(c),
              hidden: !puedeAprobar(c),
            },
          ]}
        />
      ),
    },
  ]
}
