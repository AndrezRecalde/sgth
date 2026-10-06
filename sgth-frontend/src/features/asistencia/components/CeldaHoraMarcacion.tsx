import { Group, Text } from '@mantine/core'
import { IconCalendarCheck, IconClockCheck } from '@tabler/icons-react'
import type { PermisoDelDia } from '../utils/permisosDelDia'

interface Props {
  /** `HH:mm:ss` del biométrico, o null si no hay marca. */
  hora: string | null | undefined
  /** El permiso que cubre esta parte del día, si lo hay. */
  permiso: PermisoDelDia | null
  /** La jornada entera está cubierta por un permiso. */
  diaCompleto?: boolean
  /** La celda que nombra el permiso de día completo (la de entrada). */
  nombraElDia?: boolean
  /** Atraso o salida anticipada; no se muestra si el permiso lo cubre. */
  senal?: React.ReactNode
}

/**
 * Una celda de hora de la tabla de marcaciones.
 *
 * Una hora vacía que un permiso cubre no es una marca que falta: se dice
 * «Con permiso» en vez de pintar un guion. En un día de permiso completo la
 * celda de entrada lo nombra y las demás se atenúan, porque `SgthTable` no
 * puede unir celdas.
 */
export function CeldaHoraMarcacion({ hora, permiso, diaCompleto = false, nombraElDia = false, senal }: Props) {
  if (diaCompleto && permiso && !hora) {
    return nombraElDia ? (
      <Group gap={4} wrap="nowrap">
        <IconCalendarCheck size={14} aria-hidden />
        <Text size="xs" c="dimmed" fs="italic">{permiso.nombre} · todo el día</Text>
      </Group>
    ) : (
      <Text size="sm" c="dimmed">—</Text>
    )
  }

  if (!hora && permiso) {
    return (
      <Group gap={4} wrap="nowrap">
        <IconClockCheck size={14} aria-hidden />
        <Text size="xs" c="dimmed" fs="italic">Con permiso</Text>
      </Group>
    )
  }

  return (
    <Group gap={6} wrap="nowrap">
      <Text size="sm">{hora ? hora.substring(0, 5) : '—'}</Text>
      {permiso ? (
        <Text size="xs" c="dimmed" fs="italic">con permiso</Text>
      ) : (
        senal
      )}
    </Group>
  )
}
