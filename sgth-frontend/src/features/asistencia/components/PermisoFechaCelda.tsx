'use client'

import { Stack, Text } from '@mantine/core'
import { formatFecha } from '@/lib/fecha'
import type { PermisoServidor } from '@/types/api'

/**
 * La fecha del permiso en las dos tablas —la de Talento Humano y «Mis
 * permisos»—. Si viene de un certificado médico, también el último día del
 * reposo: el permiso guarda solo el primero, y un reposo del 19 al 21 se leía
 * como un permiso del 19.
 */
export function PermisoFechaCelda({ permiso }: { permiso: PermisoServidor }) {
  if (!permiso.fecha) return <Text size="sm" c="dimmed">—</Text>

  const certificado = permiso.certificado_medico
  const hasta = certificado && certificado.dias_reposo > 1 ? certificado.fecha_fin : null

  return (
    <Stack gap={0}>
      <Text size="sm">{formatFecha(permiso.fecha)}</Text>
      {hasta && <Text size="xs" c="dimmed">al {formatFecha(hasta)}</Text>}
    </Stack>
  )
}
