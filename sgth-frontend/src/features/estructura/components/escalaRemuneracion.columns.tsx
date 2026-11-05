import { Stack, Text } from '@mantine/core'
import { IconArrowBackUp } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { dinero } from '@/lib/dinero'
import type { EscalaRemuneracion } from '../services/escalaRemuneracionService'

type Acciones = {
  alRevertir: (escala: EscalaRemuneracion) => void
  /** `anular-accion-personal`: la revierte el Director. */
  puedeRevertir: boolean
}

export const getEscalaRemuneracionColumns = (
  { alRevertir, puedeRevertir }: Acciones,
): DataTableColumn<EscalaRemuneracion>[] => [
  {
    accessor: 'instrumento',
    title: 'Resolución',
    render: (e) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{e.instrumento}</Text>
        <Text size="xs" c="dimmed">del {formatFecha(e.fecha_instrumento)} · dictamen {e.dictamen_presupuestario_ref}</Text>
      </Stack>
    ),
  },
  { accessor: 'rige_desde', title: 'Rige desde', render: (e) => formatFecha(e.rige_desde) },
  {
    accessor: 'grados',
    title: 'Grados',
    render: (e) => (
      <Stack gap={0}>
        {e.grados.map((g) => (
          <Text key={g.grado ?? ''} size="xs">{g.grado} · {dinero(g.rmu_anterior)} → <b>{dinero(g.rmu_nueva)}</b></Text>
        ))}
      </Stack>
    ),
  },
  { accessor: 'vinculos', title: 'Servidores que subieron', textAlign: 'right' },
  {
    accessor: 'revertida_en',
    title: 'Estado',
    render: (e) => e.revertida_en
      ? <StatusBadge tone="neutral">Revertida</StatusBadge>
      : <StatusBadge tone="success">Aplicada</StatusBadge>,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (e) => (
      <TableActions
        actions={[{
          // Por permiso se oculta; la ya revertida se apaga (regla 06).
          label: 'Revertir la escala',
          icon: <IconArrowBackUp size={14} />,
          color: 'red',
          hidden: !puedeRevertir,
          disabled: !!e.revertida_en,
          onClick: () => alRevertir(e),
        }]}
      />
    ),
  },
]
