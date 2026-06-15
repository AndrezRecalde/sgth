'use client'

import { Group, Button, Badge, Tooltip } from '@mantine/core'
import {
  IconBuildingHospital,
  IconUsers,
  IconUserCircle,
} from '@tabler/icons-react'
import { useRouter, usePathname } from 'next/navigation'
import { ROUTES, type Subsistema } from '@/config/routes'
import { getSubsistemasDisponibles } from '@/config/nav'
import { useAuth } from '@/hooks/useAuth'

const SUBSISTEMA_CONFIG = {
  sgth: {
    label: 'SGTH',
    icon:  IconUsers,
    color: 'emerald',
    home:  ROUTES.SGTH.HOME,
    prefix: '/sgth',
  },
  salud: {
    label: 'Dispensario',
    icon:  IconBuildingHospital,
    color: 'blue',
    home:  ROUTES.SALUD.HOME,
    prefix: '/salud',
  },
  portal: {
    label: 'Portal',
    icon:  IconUserCircle,
    color: 'violet',
    home:  ROUTES.PORTAL.HOME,
    prefix: '/portal',
  },
} as const

export function SubsistemaHeader() {
  const router   = useRouter()
  const pathname = usePathname()
  const { usuario } = useAuth()

  const roles = (usuario?.roles as string[]) ?? []
  const disponibles = getSubsistemasDisponibles(roles)

  const subsistemaActivo = (
    Object.entries(SUBSISTEMA_CONFIG).find(
      ([, config]) => pathname.startsWith(config.prefix)
    )?.[0] as Subsistema | undefined
  )

  if (disponibles.length <= 1) return null

  return (
    <Group gap="xs">
      {disponibles.map(key => {
        const config = SUBSISTEMA_CONFIG[key]
        const Icon   = config.icon
        const activo = subsistemaActivo === key

        return (
          <Tooltip
            key={key}
            label={config.label}
            withArrow
            position="bottom"
          >
            <Button
              size="xs"
              variant={activo ? 'filled' : 'subtle'}
              color={config.color}
              leftSection={<Icon size={14} />}
              onClick={() => router.push(config.home)}
              styles={{
                root: {
                  fontWeight: activo ? 700 : 500,
                },
              }}
            >
              {config.label}
              {activo && (
                <Badge
                  size="xs"
                  color={config.color}
                  variant="white"
                  ml={4}
                >
                  activo
                </Badge>
              )}
            </Button>
          </Tooltip>
        )
      })}
    </Group>
  )
}
