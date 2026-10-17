import Link from 'next/link'
import { Anchor, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { formatFechaHora } from '@/lib/fecha'
import { RESULTADO_CONSTATACION } from '../constants/constatacion'
import type { Constatacion, FilaConstatacion } from '../services/constatacionesService'

export const columnasConstataciones: DataTableColumn<Constatacion>[] = [
  {
    accessor: 'nombre',
    title: 'Constatación',
    render: (c) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{c.nombre}</Text>
        <Text size="xs" c="dimmed">
          {c.ubicacion}{c.unidad ? ` · ${c.unidad}` : ''}{c.participa_activos_fijos ? ' · Con Activos Fijos y Bodega' : ''}
        </Text>
      </Stack>
    ),
  },
  {
    accessor: 'iniciada_en',
    title: 'Iniciada',
    width: 200,
    render: (c) => (
      <Stack gap={0}>
        <Text size="sm">{formatFechaHora(c.iniciada_en)}</Text>
        <Text size="xs" c="dimmed">{c.iniciada_por ?? '—'}</Text>
      </Stack>
    ),
  },
  { accessor: 'escaneados', title: 'Escaneados', width: 110, render: (c) => c.escaneados ?? 0 },
  {
    accessor: 'no_encontrados',
    title: 'No encontrados',
    width: 140,
    render: (c) => (c.estado === 'cerrada' ? (c.no_encontrados ?? 0) : '—'),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 120,
    render: (c) => <StatusBadge tone={c.estado === 'en_curso' ? 'info' : 'neutral'}>{c.estado === 'en_curso' ? 'En curso' : 'Cerrada'}</StatusBadge>,
  },
]

export const columnasFilasConstatacion: DataTableColumn<FilaConstatacion>[] = [
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
        <Text size="xs" c="dimmed">{f.custodio ?? 'Sin custodio'}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'resultado',
    title: 'Resultado',
    width: 200,
    render: (f) => (
      <Stack gap={2}>
        <StatusBadge tone={RESULTADO_CONSTATACION[f.resultado].tono}>{RESULTADO_CONSTATACION[f.resultado].etiqueta}</StatusBadge>
        {f.resultado === 'otra_ubicacion' && (
          <Text size="xs" c="dimmed">{f.movido ? 'Se movió aquí' : `Registrado en ${f.ubicacion_registrada ?? '—'}`}</Text>
        )}
      </Stack>
    ),
  },
  {
    accessor: 'escaneado_en',
    title: 'Escaneado',
    width: 180,
    render: (f) => (f.escaneado_en
      ? <Stack gap={0}><Text size="sm">{formatFechaHora(f.escaneado_en)}</Text><Text size="xs" c="dimmed">{f.escaneado_por ?? ''}</Text></Stack>
      : '—'),
  },
]
