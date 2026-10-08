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

  return (
    <Group gap={4} wrap="nowrap">
      <IconFingerprint size={12} color="var(--mantine-color-dimmed)" />
      <Text size="xs" c="dimmed" lineClamp={1} title={permiso.sirha7_leave_nombre ?? undefined}>
        En Sirha7: {permiso.sirha7_leave_nombre}
      </Text>
    </Group>
  )
}
