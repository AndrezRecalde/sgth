'use client'

import { Group, Text } from '@mantine/core'
import { IconFingerprint } from '@tabler/icons-react'
import type { PermisoServidor } from '@/types/api'

/**
 * «En Sirha7: <tipo>» debajo del estado, cuando el permiso ya se aprobó en el
 * biométrico. Lo ve también el servidor en «Mis permisos»: es lo que justifica
 * su ausencia en las marcaciones.
 */
export function EnSirha7Texto({ permiso }: { permiso: PermisoServidor }) {
  if (!permiso.sirha7_aprobado_en) return null

  // Sin recortar: el tipo es lo que TH eligió y tiene que leerse entero.
  // Recortado a una línea, «COMISION DE SERVICIOS» se quedaba en «COMISION…».
  // Si no cabe en la columna, pasa a la línea siguiente.
  return (
    <Group gap={4} wrap="nowrap" align="flex-start">
      <IconFingerprint size={12} color="var(--mantine-color-dimmed)" style={{ flexShrink: 0, marginTop: 2 }} />
      <Text size="xs" c="dimmed">
        En Sirha7: {permiso.sirha7_leave_nombre}
      </Text>
    </Group>
  )
}
