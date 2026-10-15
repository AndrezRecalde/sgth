import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { TONO_RESULTADO_CARGA } from '../constants/resultadosCarga'
import type { ArchivoCarga } from '../services/tipos'

/** Qué pasa (o pasó) con cada archivo de la carga masiva. */
export const columnasInformeCarga: DataTableColumn<ArchivoCarga>[] = [
  {
    accessor: 'archivo',
    title: 'Archivo',
    render: (a) => <Text size="sm" style={{ wordBreak: 'break-all' }}>{a.archivo}</Text>,
  },
  {
    accessor: 'servidor',
    title: 'Servidor',
    render: (a) => a.servidor ? (
      <Stack gap={0}>
        <Text size="sm">{a.servidor.nombre_completo}</Text>
        <Text size="xs" c="dimmed">{a.servidor.unidad ?? a.cedula}</Text>
      </Stack>
    ) : (
      <Text size="sm" c="dimmed">{a.cedula ?? '—'}</Text>
    ),
  },
  {
    accessor: 'resultado',
    title: 'Resultado',
    width: 230,
    render: (a) => (
      <Stack gap={2}>
        <StatusBadge tone={TONO_RESULTADO_CARGA[a.resultado]} size="xs">{a.resultado_etiqueta}</StatusBadge>
        {a.detalle && <Text size="xs" c="dimmed">{a.detalle}</Text>}
      </Stack>
    ),
  },
]
