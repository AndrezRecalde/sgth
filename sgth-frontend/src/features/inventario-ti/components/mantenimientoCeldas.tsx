import { Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { Mantenimiento } from '../services/tipos'

/*
| Las celdas de un mantenimiento, compartidas por la ficha del equipo y la
| lista general. Un anulado se ve tachado de sentido —«Anulado» y su motivo—,
| no desaparece: el historial no esconde lo que se corrigió.
*/

export function celdaFechaMantenimiento(m: Mantenimiento) {
  return m.fecha_mantenimiento
    ? formatFecha(m.fecha_mantenimiento)
    : <Text size="xs" c="dimmed">No consta</Text>
}

export function celdaTipoMantenimiento(m: Mantenimiento) {
  return (
    <Stack gap={4} align="flex-start">
      <StatusBadge>{m.tipo_etiqueta}</StatusBadge>
      {m.anulado ? <StatusBadge tone="danger">Anulado</StatusBadge> : null}
    </Stack>
  )
}

export function celdaDescripcionMantenimiento(m: Mantenimiento) {
  return (
    <Stack gap={0}>
      <Text size="sm" c={m.anulado ? 'dimmed' : undefined}>{m.descripcion}</Text>
      {m.soporte_anterior ? <Text size="xs" c="dimmed">Soporte N.º {m.soporte_anterior} (sistema anterior)</Text> : null}
      {m.anulado ? <Text size="xs" c="red">Anulado: {m.motivo_anulacion}</Text> : null}
    </Stack>
  )
}

export function celdaQuienMantenimiento(m: Mantenimiento) {
  if (m.realizado_por === 'proveedor') {
    return (
      <Stack gap={0}>
        <Text size="sm">Proveedor</Text>
        {m.contrato_vigencia ? <Text size="xs" c="dimmed">{m.contrato_vigencia.proceso}</Text> : null}
      </Stack>
    )
  }

  return m.tecnico?.nombre ?? <Text size="xs" c="dimmed">TIC</Text>
}
