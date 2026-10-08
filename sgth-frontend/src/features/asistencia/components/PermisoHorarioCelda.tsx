'use client'

import { Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { duracion } from '../utils/horarioPermiso'
import type { PermisoServidor } from '@/types/api'

/**
 * El horario del permiso en las dos tablas. El de un certificado médico cubre
 * la jornada entera de cada día de reposo: se guarda como 00:00–23:59, y la
 * tabla lo enseñaba así, con «23h 59m», en vez de los días.
 */
export function PermisoHorarioCelda({ permiso }: { permiso: PermisoServidor }) {
  const { hora_inicio, hora_fin, certificado_medico: certificado } = permiso

  if (certificado) {
    return (
      <Stack gap={2}>
        <Text size="sm">Jornada completa</Text>
        <StatusBadge size="xs">
          {certificado.dias_reposo === 1 ? '1 día' : `${certificado.dias_reposo} días`}
        </StatusBadge>
      </Stack>
    )
  }

  if (!hora_inicio || !hora_fin) return <Text size="sm" c="dimmed">—</Text>

  return (
    <Stack gap={2}>
      <Text size="sm" ff="monospace">
        {hora_inicio.substring(0, 5)} — {hora_fin.substring(0, 5)}
      </Text>
      <StatusBadge size="xs">
        {duracion(hora_inicio, hora_fin)}
      </StatusBadge>
    </Stack>
  )
}
