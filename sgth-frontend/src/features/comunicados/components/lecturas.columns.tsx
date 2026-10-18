import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFechaMesHora } from '@/lib/fecha'
import { TONO_LECTURA } from '../constants/comunicados'
import type { EstadoLectura, LecturaDestinatario } from '../services/tipos'

/** Sin confirmación que pedir, «leído» ya es el final del camino. */
export function etiquetaLectura(estado: EstadoLectura, requiereConfirmacion: boolean): string {
  if (estado === 'confirmado') return 'Confirmado'
  if (estado === 'leido') return requiereConfirmacion ? 'Leído, sin confirmar' : 'Leído'
  return 'Sin leer'
}

export function columnasLecturas(requiereConfirmacion: boolean): DataTableColumn<LecturaDestinatario>[] {
  const columnas: DataTableColumn<LecturaDestinatario>[] = [
    {
      accessor: 'nombre',
      title: 'Servidor',
      render: (l) => (
        <Stack gap={0}>
          <Text size="sm" fw={600}>{l.nombre}</Text>
          {l.cedula && <Text size="xs" c="dimmed">{l.cedula}</Text>}
        </Stack>
      ),
    },
    {
      accessor: 'unidad',
      title: 'Unidad',
      render: (l) => <Text size="sm" c={l.unidad ? undefined : 'dimmed'}>{l.unidad ?? 'Sin unidad'}</Text>,
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 180,
      render: (l) => <StatusBadge tone={TONO_LECTURA[l.estado]}>{etiquetaLectura(l.estado, requiereConfirmacion)}</StatusBadge>,
    },
    {
      accessor: 'leido_at',
      title: 'Leído el',
      width: 190,
      render: (l) => <Text size="sm">{formatFechaMesHora(l.leido_at)}</Text>,
    },
  ]

  if (requiereConfirmacion) {
    columnas.push({
      accessor: 'confirmado_at',
      title: 'Confirmado el',
      width: 190,
      render: (l) => <Text size="sm">{formatFechaMesHora(l.confirmado_at)}</Text>,
    })
  }

  return columnas
}
