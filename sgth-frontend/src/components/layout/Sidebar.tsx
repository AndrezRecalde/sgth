'use client'

import { Box, ScrollArea } from '@mantine/core'
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
  const { usuario } = useAuth()
  const groups = buildNavGroups(usuario?.permisos || [])

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
    </Box>
  )
}

