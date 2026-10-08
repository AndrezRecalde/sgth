'use client'

import { Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { duracion } from '../utils/horarioPermiso'
import type { PermisoServidor } from '@/types/api'

/** El horario del permiso y su duración, en las dos tablas de permisos. */
export function PermisoHorarioCelda({ permiso }: { permiso: PermisoServidor }) {
  const { hora_inicio, hora_fin } = permiso

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
