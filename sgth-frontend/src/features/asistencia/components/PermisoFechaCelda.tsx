'use client'

import { Text } from '@mantine/core'
import { formatFecha } from '@/lib/fecha'
import type { PermisoServidor } from '@/types/api'

/**
 * La fecha del permiso en las dos tablas —la de Talento Humano y «Mis
 * permisos»—. Los reposos de varios días ya no son permisos (2026-10-08):
 * salen en su viñeta y en «Mis reposos médicos», con su rango.
 */
export function PermisoFechaCelda({ permiso }: { permiso: PermisoServidor }) {
  if (!permiso.fecha) return <Text size="sm" c="dimmed">—</Text>

  return <Text size="sm">{formatFecha(permiso.fecha)}</Text>
}
