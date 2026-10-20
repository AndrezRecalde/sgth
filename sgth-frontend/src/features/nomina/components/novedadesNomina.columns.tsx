import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { SemanticTone } from '@/config/design.tokens'
import type { ConceptoNovedadNomina, NovedadNomina } from '../services/novedadesNominaService'

/** El tono dice si la novedad suma al rol (verde) o le resta (rojo). */
const TONO: Record<ConceptoNovedadNomina, SemanticTone> = {
  ingreso: 'info',
  salida: 'neutral',
  cambio_remuneracion: 'info',
  dias_sin_remuneracion: 'danger',
  subrogacion: 'success',
  encargo: 'success',
  multa: 'danger',
  remuneraciones_pendientes: 'success',
  vacaciones_no_gozadas: 'success',
  indemnizacion: 'success',
}

function periodo(n: NovedadNomina): string {
  if (!n.desde) return '—'
  if (!n.hasta || n.hasta === n.desde) return formatFecha(n.desde)

  return `${formatFecha(n.desde)} – ${formatFecha(n.hasta)}`
}

export const columnasNovedadesNomina: DataTableColumn<NovedadNomina>[] = [
  {
    accessor: 'servidor',
    title: 'Servidor',
    render: (n) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{n.servidor}</Text>
        <Text size="xs" c="dimmed">{n.cedula ?? '—'}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'concepto',
    title: 'Novedad',
    width: '40%',
    render: (n) => (
      <Stack gap={4}>
        <StatusBadge tone={TONO[n.concepto]}>{n.concepto_etiqueta}</StatusBadge>
        <Text size="xs">{n.detalle}</Text>
      </Stack>
    ),
  },
  { accessor: 'desde', title: 'Período', render: periodo },
  { accessor: 'dias', title: 'Días', textAlign: 'right', render: (n) => n.dias ?? '—' },
  { accessor: 'acto', title: 'Acto', render: (n) => n.acto ?? '—' },
]
