'use client'

import { Box, ScrollArea, Text } from '@mantine/core'
import { usePathname }           from 'next/navigation'
import { useAuth }               from '@/hooks/useAuth'
import { buildNav }              from '@/config/nav'
import { NavGroup }              from './NavGroup'
import { NavItem }               from './NavItem'
import classes                   from './Sidebar.module.css'
import type { Subsistema }       from '@/config/routes'

interface Props {
  collapsed:   boolean
  onNavClick?: () => void
}

function getSubsistema(pathname: string): Subsistema {
  if (pathname.startsWith('/salud'))  return 'salud'
  if (pathname.startsWith('/portal')) return 'portal'
  return 'sgth'
}

const SUBSISTEMA_LABELS: Record<Subsistema, string> = {
  sgth:   'Talento Humano',
  salud:  'Dispensario Médico',
  portal: 'Portal Servidor',
}

const SUBSISTEMA_COLORS: Record<Subsistema, string> = {
  sgth:   'var(--mantine-color-emerald-6)',
  salud:  'var(--mantine-color-blue-6)',
  portal: 'var(--mantine-color-violet-6)',
}

export function Sidebar({ collapsed, onNavClick }: Props) {
  const { usuario } = useAuth()
  const pathname    = usePathname()

  const subsistema = getSubsistema(pathname)
  const permisos   = usuario?.permisos ?? []
  const groups     = buildNav(subsistema, permisos)

  return (
    <Box className={classes.sidebar}>
      {!collapsed && (
        <Box
          px="md"
          py="xs"
          style={{
            borderBottom: '1px solid var(--mantine-color-gray-2)',
          }}
        >
          <Text
            size="xs"
            fw={700}
            tt="uppercase"
            style={{ color: SUBSISTEMA_COLORS[subsistema] }}
          >
            {SUBSISTEMA_LABELS[subsistema]}
          </Text>
        </Box>
      )}

      <ScrollArea style={{ flex: 1 }} px="xs">
        {groups.map((g, i) => (
          <Box key={i} pb="sm">
            <NavGroup label={g.label} collapsed={collapsed} />
            {g.items.map((item) => (
              <NavItem
                key={item.href}
                {...item}
                collapsed={collapsed}
                onClick={onNavClick}
              />
            ))}
          </Box>
        ))}
      </ScrollArea>
    </Box>
  )
}
