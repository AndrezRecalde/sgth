import { Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { MarcacionBiometrica } from '@/types/api'
import { motivoPorRevisar, permisosDelDia, permisoPorCelda } from '../utils/permisosDelDia'
import { CeldaHoraMarcacion } from './CeldaHoraMarcacion'

const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']

const hhmm = (h?: string | null) => (h ? h.substring(0, 5) : '')

/**
 * Minutos de atraso que pasan la tolerancia del horario, o 0. El procedimiento
 * los da completos y aparte la tolerancia.
 */
function atrasoSobreTolerancia(fila: MarcacionBiometrica): number {
  const atraso = Number(fila.MinutosAtraso ?? 0)
  return atraso > Number(fila.ToleranciaAtraso ?? 0) ? atraso : 0
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
          <Text size="sm">{hhmm(HoraEntradaProgramada)}–{hhmm(HoraSalidaProgramada)}</Text>
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
    width: 170,
    render: (fila) => {
      const { dia, entrada } = permisoPorCelda(fila)
      const atraso = atrasoSobreTolerancia(fila)
      return (
        <CeldaHoraMarcacion
          hora={fila.Entrada}
          permiso={entrada}
          diaCompleto={dia}
          nombraElDia
          senal={atraso > 0 && <StatusBadge tone="warning" size="xs">+{atraso} min</StatusBadge>}
        />
      )
    },
  },
  {
    accessor: 'AlmuerzoSalida',
    title: 'Salida almuerzo',
    width: 120,
    render: (fila) => {
      const { dia, almuerzo } = permisoPorCelda(fila)
      return <CeldaHoraMarcacion hora={fila.AlmuerzoSalida} permiso={fila.AlmuerzoSalida ? null : almuerzo} diaCompleto={dia} />
    },
  },
  {
    accessor: 'AlmuerzoRetorno',
    title: 'Retorno almuerzo',
    width: 120,
    render: (fila) => {
      const { dia, almuerzo } = permisoPorCelda(fila)
      return <CeldaHoraMarcacion hora={fila.AlmuerzoRetorno} permiso={fila.AlmuerzoRetorno ? null : almuerzo} diaCompleto={dia} />
    },
  },
  {
    accessor: 'Salida',
    title: 'Salida',
    width: 140,
    render: (fila) => {
      const { dia, salida } = permisoPorCelda(fila)
      const anticipada = Number(fila.MinutosSalidaAnticipada ?? 0)
      return (
        <CeldaHoraMarcacion
          hora={fila.Salida}
          permiso={salida}
          diaCompleto={dia}
          senal={anticipada > 0 && <StatusBadge tone="warning" size="xs">−{anticipada} min</StatusBadge>}
        />
      )
    },
  },
  {
    accessor: 'TipoPermiso',
    title: 'Permisos',
    width: 180,
    render: (fila) => (
      <Stack gap={4}>
        {/* Categorías: van sin tono (regla 06). Debajo, el horario, para ver
            si cubre el atraso o la salida temprana. */}
        {permisosDelDia(fila).map((p, i) => (
          <Stack key={`${p.nombre}-${i}`} gap={0} align="flex-start">
            <StatusBadge size="xs">{p.nombre}</StatusBadge>
            {p.desde && p.hasta && <Text size="xs" c="dimmed">{p.desde}–{p.hasta}</Text>}
          </Stack>
        ))}
      </Stack>
    ),
  },
  {
    accessor: 'MarcasPorRevisar',
    title: 'Por revisar',
    render: ({ MarcasPorRevisar }) => (
      <Stack gap={4}>
        {/* Teclas probablemente equivocadas, para corregir en el biométrico.
            Con el motivo: la letra sola no decía qué estaba mal. */}
        {(MarcasPorRevisar ? MarcasPorRevisar.split(', ') : []).map((marca, i) => {
          const { hora, motivo } = motivoPorRevisar(marca)
          return (
            <Group key={`${marca}-${i}`} gap={4} wrap="nowrap">
              <StatusBadge tone="warning" size="xs" leftSection={<IconAlertTriangle size={12} />}>
                {hora}
              </StatusBadge>
              <Text size="xs" c="dimmed">{motivo}</Text>
            </Group>
          )
        })}
      </Stack>
    ),
  },
]
