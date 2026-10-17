import Link from 'next/link'
import { Anchor, Group, Progress, Stack, Text } from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { formatFecha } from '@/lib/fecha'
import { plural } from '@/lib/plural'
import { ESTADO_EQUIPO_CAMPANIA, SITUACION_CAMPANIA } from '../constants/campania'
import type { Campania, FilaCampania } from '../services/campaniasService'
import { lugarDeCampania, quienHaceCampania } from '../utils/campania'

/** El avance de una campaña: hechos sobre el total, y los omitidos aparte. */
export function AvanceCampania({ hechos, total, omitidos }: { hechos: number; total: number; omitidos: number }) {
  const pct = total ? Math.round((hechos / total) * 100) : 0

  return (
    <Stack gap={4}>
      <Group gap={4} justify="space-between">
        <Text size="xs" fw={600}>{hechos} de {total}</Text>
        <Text size="xs" c="dimmed">{pct}%</Text>
      </Group>
      <Progress value={pct} size="sm" radius="sm" color={SEMANTIC_COLOR[pct === 100 ? 'success' : 'info']} />
      {omitidos > 0 && <Text size="xs" c="dimmed">{plural(omitidos, 'omitido', 'omitidos')}</Text>}
    </Stack>
  )
}

export const columnasCampanias: DataTableColumn<Campania>[] = [
  {
    accessor: 'nombre',
    title: 'Campaña',
    render: (c) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{c.nombre}</Text>
        <Text size="xs" c="dimmed">{lugarDeCampania(c)}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'tecnico',
    title: 'Quién la hace',
    width: 220,
    render: (c) => {
      const { principal, detalle } = quienHaceCampania(c)
      return (
        <Stack gap={0}>
          <Text size="sm">{principal}</Text>
          {detalle && <Text size="xs" c="dimmed">{detalle}</Text>}
        </Stack>
      )
    },
  },
  {
    accessor: 'fecha_inicio',
    title: 'Período',
    width: 200,
    render: (c) => `${formatFecha(c.fecha_inicio)} – ${formatFecha(c.fecha_fin)}`,
  },
  {
    accessor: 'hechos',
    title: 'Avance',
    width: 170,
    render: (c) => <AvanceCampania hechos={c.hechos ?? 0} total={c.total ?? 0} omitidos={c.omitidos ?? 0} />,
  },
  {
    accessor: 'situacion',
    title: 'Situación',
    width: 120,
    render: (c) => <StatusBadge tone={SITUACION_CAMPANIA[c.situacion].tono}>{SITUACION_CAMPANIA[c.situacion].etiqueta}</StatusBadge>,
  },
]

interface Opciones {
  /** Quitar un equipo de la campaña; sin él, no hay columna de acciones. */
  onQuitar?: (f: FilaCampania) => void
}

export const columnasFilasCampania = ({ onQuitar }: Opciones): DataTableColumn<FilaCampania>[] => [
  {
    accessor: 'codigo_institucional',
    title: 'Código',
    width: 100,
    render: (f) => <Anchor size="sm" component={Link} href={ROUTES.TIC.EQUIPO(f.bien_id)}>{f.codigo_institucional}</Anchor>,
  },
  {
    accessor: 'equipo',
    title: 'Equipo',
    render: (f) => (
      <Stack gap={0}>
        <Text size="sm">{f.equipo || '—'}</Text>
        <Text size="xs" c="dimmed">{f.custodio ?? 'Sin custodio'}{f.ubicacion ? ` · ${f.ubicacion}` : ''}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 230,
    render: (f) => (
      <Stack gap={2}>
        <StatusBadge tone={ESTADO_EQUIPO_CAMPANIA[f.estado].tono}>{ESTADO_EQUIPO_CAMPANIA[f.estado].etiqueta}</StatusBadge>
        {f.motivo_omision && <Text size="xs" c="dimmed">{f.motivo_omision}</Text>}
        {f.anulado && <Text size="xs" c="dimmed">Su mantenimiento se anuló desde la ficha.</Text>}
      </Stack>
    ),
  },
  {
    accessor: 'fecha',
    title: 'Hecho',
    width: 200,
    render: (f) => (f.fecha
      ? <Stack gap={0}><Text size="sm">{formatFecha(f.fecha)}</Text><Text size="xs" c="dimmed">{f.tecnico ?? ''}</Text></Stack>
      : '—'),
  },
  ...(onQuitar
    ? [{
        accessor: 'acciones',
        title: '',
        width: 50,
        render: (f: FilaCampania) => (
          <TableActions actions={[{
            label: 'Quitar de la campaña', icon: <IconTrash size={14} />, color: 'red',
            disabled: f.estado === 'hecho', onClick: () => onQuitar(f),
          }]} />
        ),
      }]
    : []),
]
