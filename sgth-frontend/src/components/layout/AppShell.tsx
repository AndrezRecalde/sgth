'use client'

import { AppShell } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { Sidebar } from './Sidebar'
import { Topbar } from './Topbar'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

export function SGTHAppShell({ children }: { children: React.ReactNode }) {
  const [opened, { toggle }] = useDisclosure()
  const { isTablet, isMobile } = useMobileBreakpoint()

  return (
    <AppShell
      header={{ height: 52 }}
      navbar={{
        width: isTablet ? 60 : 220,
        breakpoint: 'md',
        collapsed: { mobile: !opened },
      }}
      padding="md"
    >
      <AppShell.Header>
        <Topbar opened={opened} onBurgerClick={toggle} />
      </AppShell.Header>

      <AppShell.Navbar>
        <Sidebar collapsed={isTablet && !isMobile} onNavClick={() => isMobile && toggle()} />
      </AppShell.Navbar>

      <AppShell.Main>
        {children}
      </AppShell.Main>
    </AppShell>
  )
}
