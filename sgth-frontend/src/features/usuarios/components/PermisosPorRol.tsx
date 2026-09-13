'use client'

import { useState } from 'react'
import { Collapse, Divider, Group, Stack, Text, UnstyledButton } from '@mantine/core'
import { IconChevronDown } from '@tabler/icons-react'
import { etiquetaRol } from '../constants/roles'
import { etiquetaPermiso } from '../constants/permisos'
import { StatusBadge } from '@/components/ui'

interface Props {
  roles: string[]
  permisosCubiertos: Set<string>
}

/**
 * Lo que el usuario ya tiene por sus roles, en solo lectura.
 *
 * Va colapsado porque un admin acumula ~84 permisos por rol y pintarlos
 * siempre convertía la cabecera del panel en un muro de insignias que había
 * que scrollear entero para llegar a lo editable.
 */
export function PermisosPorRol({ roles, permisosCubiertos }: Props) {
  const [abierto, setAbierto] = useState(false)

  if (roles.length === 0) return null

  return (
    <>
      <UnstyledButton
        onClick={() => setAbierto(v => !v)}
        aria-expanded={abierto}
      >
        <Group gap="xs" wrap="nowrap">
          <IconChevronDown
            size={14}
            style={{
              transform: abierto ? 'rotate(0deg)' : 'rotate(-90deg)',
              transition: 'transform 150ms ease',
            }}
          />
          <Text size="xs" fw={600} c="dimmed">
            {permisosCubiertos.size} PERMISOS YA CONCEDIDOS POR SUS ROLES
          </Text>
        </Group>
      </UnstyledButton>

      <Collapse expanded={abierto}>
        <Stack gap="xs">
          <Group gap={4} wrap="wrap">
            {roles.map(r => (
              <StatusBadge key={r} size="xs">
                {etiquetaRol(r)}
              </StatusBadge>
            ))}
          </Group>
          <Group gap={4} wrap="wrap">
            {Array.from(permisosCubiertos).sort().map(p => (
              <StatusBadge key={p} size="xs">
                {etiquetaPermiso(p)}
              </StatusBadge>
            ))}
          </Group>
        </Stack>
      </Collapse>

      <Divider />
    </>
  )
}
