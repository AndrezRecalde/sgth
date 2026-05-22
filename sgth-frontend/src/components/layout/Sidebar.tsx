'use client'

import { Box, ScrollArea } from '@mantine/core'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/hooks/useAuth'
import { buildNavGroups } from '@/config/nav'
//import { SidebarSystemSelector } from './SidebarSystemSelector'
import { SidebarUserRow } from './SidebarUserRow'
import { NavGroup } from './NavGroup'
import { NavItem } from './NavItem'
import classes from './Sidebar.module.css'

interface Props {
  collapsed: boolean
  onNavClick?: () => void
}

export function Sidebar({ collapsed, onNavClick }: Props) {
  const router = useRouter()
  const { clearAuth, usuario } = useAuth()
  const groups = buildNavGroups(usuario?.permisos || [])

  const handleLogout = (e?: React.MouseEvent) => {
    e?.preventDefault()
    clearAuth()
    router.push('/login')
    if (onNavClick) onNavClick()
  }

  return (
    <Box className={classes.sidebar}>
      {/* <SidebarSystemSelector collapsed={collapsed} /> */}
      <SidebarUserRow collapsed={collapsed} />
      
      <ScrollArea style={{ flex: 1 }} px="xs">
        {groups.map((g, i) => (
          <Box key={i} pb="sm">
            <NavGroup label={g.label} collapsed={collapsed} />
            {g.items.map(item => (
              <NavItem key={item.href} {...item} collapsed={collapsed} onClick={onNavClick} />
            ))}
          </Box>
        ))}
      </ScrollArea>

      <Box className={classes.footer}>
        <NavItem label="Configuración" icon="IconSettings" href="/configuracion" collapsed={collapsed} onClick={onNavClick} />
        <NavItem label="Cerrar sesión" icon="IconLogout" href="#" collapsed={collapsed} onClick={handleLogout} />
      </Box>
    </Box>
  )
}
