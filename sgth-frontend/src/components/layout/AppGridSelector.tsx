'use client'

import {
  Menu, ActionIcon, Tooltip, Text,
  Group, ThemeIcon, Stack,
} from '@mantine/core'
import {
  IconGridDots, IconUsers,
  IconBuildingHospital, IconUserCircle,
} from '@tabler/icons-react'
import { useRouter, usePathname } from 'next/navigation'
import { useAuth }    from '@/hooks/useAuth'
import { ROUTES }     from '@/config/routes'
import {
  getSubsistemasDisponibles,
} from '@/config/nav'

const SUBSISTEMA_CONFIG = {
  sgth: {
    label:       'SGTH',
    descripcion: 'Talento Humano',
    icon:        IconUsers,
    color:       'emerald',
    home:        ROUTES.SGTH.HOME,
    prefix:      '/sgth',
  },
  salud: {
    label:       'Dispensario',
    descripcion: 'Salud Ambulatoria',
    icon:        IconBuildingHospital,
    color:       'blue',
    home:        ROUTES.SALUD.HOME,
    prefix:      '/salud',
  },
  portal: {
    label:       'Portal',
    descripcion: 'Mi espacio personal',
    icon:        IconUserCircle,
    color:       'violet',
    home:        ROUTES.PORTAL.HOME,
    prefix:      '/portal',
  },
} as const

type SubsistemaKey = keyof typeof SUBSISTEMA_CONFIG

export function AppGridSelector() {
  const router      = useRouter()
  const pathname    = usePathname()
  const { usuario } = useAuth()

  const roles       = (usuario?.roles as string[]) ?? []
  const disponibles = getSubsistemasDisponibles(roles)

  // Solo mostrar si tiene MÁS de un subsistema
  // (es decir, tiene un rol adicional a "servidor")
  const tieneRolAdicional = disponibles.length > 1

  if (!tieneRolAdicional) return null

  const subsistemaActivo = (
    Object.entries(SUBSISTEMA_CONFIG).find(
      ([, config]) => pathname.startsWith(config.prefix)
    )?.[0] as SubsistemaKey | undefined
  )

  return (
    <Menu
      width={260}
      position="bottom-end"
      shadow="md"
      radius="md"
    >
      <Menu.Target>
        <Tooltip label="Otros subsistemas" withArrow>
          <ActionIcon
            variant="subtle"
            color="gray"
            size="lg"
            radius="xl"
            aria-label="Subsistemas"
          >
            <IconGridDots size={22} />
          </ActionIcon>
        </Tooltip>
      </Menu.Target>

      <Menu.Dropdown>
        <Menu.Label>Subsistemas disponibles</Menu.Label>
        {disponibles.map(key => {
          const cfg    = SUBSISTEMA_CONFIG[key]
          const Icon   = cfg.icon
          const activo = subsistemaActivo === key

          return (
            <Menu.Item
              key={key}
              onClick={() => router.push(cfg.home)}
              style={{
                backgroundColor: activo
                  ? `var(--mantine-color-${cfg.color}-0)`
                  : undefined,
              }}
            >
              <Group gap="sm" wrap="nowrap">
                <ThemeIcon
                  color={cfg.color}
                  variant="light"
                  size="md"
                  radius="md"
                >
                  <Icon size={16} />
                </ThemeIcon>
                <Stack gap={0}>
                  <Text size="sm" fw={500}>
                    {cfg.label}
                  </Text>
                  <Text size="xs" c="dimmed">
                    {cfg.descripcion}
                  </Text>
                </Stack>
              </Group>
            </Menu.Item>
          )
        })}
      </Menu.Dropdown>
    </Menu>
  )
}
