import { Group, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { MarcacionBiometrica } from '@/types/api'

const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']

/** `HH:mm:ss` → `HH:mm`; un guion si no hay marca. */
function hora(h?: string | null): string {
  return h ? h.substring(0, 5) : '—'
}

/** Las listas del procedimiento llegan unidas por coma: «A, B». */
function lista(valor?: string | null): string[] {
  return valor ? valor.split(', ').filter(Boolean) : []
}

/**
 * Minutos de atraso que pasan la tolerancia del horario, o 0. El procedimiento
 * los da completos y aparte la tolerancia: antes se pintaba en ámbar cualquier
 * entrada un segundo después de la hora, con o sin tolerancia.
 */
export function atrasoSobreTolerancia(fila: MarcacionBiometrica): number {
  const atraso = Number(fila.MinutosAtraso ?? 0)
  const tolerancia = Number(fila.ToleranciaAtraso ?? 0)
  return atraso > tolerancia ? atraso : 0
}

export const marcacionesColumns: DataTableColumn<MarcacionBiometrica>[] = [
  {
    accessor: 'Fecha',
    title: 'Fecha',
    width: 120,
    render: ({ Fecha, DiaSemana }) => (
      <Stack gap={0}>
        <Text size="sm">{formatFecha(Fecha)}</Text>
        <Text size="xs" c="dimmed">{DIAS[Number(DiaSemana)] ?? ''}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'Horario',
    title: 'Horario',
    width: 150,
    render: ({ Horario, HoraEntradaProgramada, HoraSalidaProgramada, ToleranciaAtraso }) =>
      Horario ? (
        <Stack gap={0}>
          <Text size="sm">{hora(HoraEntradaProgramada)}–{hora(HoraSalidaProgramada)}</Text>
          <Text size="xs" c="dimmed">
            {Horario}{ToleranciaAtraso ? ` · tolerancia ${ToleranciaAtraso} min` : ''}
          </Text>
        </Stack>
      ) : (
        <Text size="xs" c="dimmed">Sin horario</Text>
      ),
  },
  {
    accessor: 'Entrada',
    title: 'Entrada',
    width: 120,
    render: (fila) => {
      const atraso = atrasoSobreTolerancia(fila)
      return (
        <Group gap={6} wrap="nowrap">
          <Text size="sm">{hora(fila.Entrada)}</Text>
          {atraso > 0 && (
            <StatusBadge tone="warning" size="xs">
              +{atraso} min
            </StatusBadge>
          )}
        </Group>
      )
    },
  },
  {
    accessor: 'AlmuerzoSalida',
    title: 'Salida almuerzo',
    width: 110,
    render: ({ AlmuerzoSalida }) => <Text size="sm">{hora(AlmuerzoSalida)}</Text>,
  },
  {
    accessor: 'AlmuerzoRetorno',
    title: 'Retorno almuerzo',
    width: 110,
    render: ({ AlmuerzoRetorno }) => <Text size="sm">{hora(AlmuerzoRetorno)}</Text>,
  },
  {
    accessor: 'Salida',
    title: 'Salida',
    width: 120,
    render: ({ Salida, MinutosSalidaAnticipada }) => {
      const anticipada = Number(MinutosSalidaAnticipada ?? 0)
      return (
        <Group gap={6} wrap="nowrap">
          <Text size="sm">{hora(Salida)}</Text>
          {anticipada > 0 && (
            <StatusBadge tone="warning" size="xs">
              −{anticipada} min
            </StatusBadge>
          )}
        </Group>
      )
    },
  },
  {
    accessor: 'TipoPermiso',
    title: 'Permisos',
    render: ({ TipoPermiso }) => (
      <Group gap={4}>
        {/* Categorías: van sin tono (regla 06). Antes varios permisos del
            mismo día salían juntos en una sola etiqueta. */}
        {lista(TipoPermiso).map((permiso, i) => (
          <StatusBadge key={`${permiso}-${i}`} size="xs">{permiso}</StatusBadge>
        ))}
      </Group>
    ),
  },
  {
    accessor: 'MarcasPorRevisar',
    title: 'Por revisar',
    render: ({ MarcasPorRevisar }) => (
      <Group gap={4}>
        {/* Teclas probablemente equivocadas que Talento Humano corrige en el
            biométrico: una «O» fuera del almuerzo, una «I» a mitad del día. */}
        {lista(MarcasPorRevisar).map((marca, i) => (
          <StatusBadge key={`${marca}-${i}`} tone="warning" variant="outline" size="xs">
            {marca}
          </StatusBadge>
        ))}
      </Group>
    ),
  },
]
