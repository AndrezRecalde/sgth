'use client'

import { AppShell } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { Sidebar } from './Sidebar'
import { Topbar } from './Topbar'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

export function SGTHAppShell({ children }: { children: React.ReactNode }) {
  const [mobileOpened, { toggle: toggleMobile }] = useDisclosure(false)
  const [desktopOpened, { toggle: toggleDesktop }] = useDisclosure(true)
  const { isTablet, isMobile } = useMobileBreakpoint()

  return (
    <AppShell
      header={{ height: 52 }}
      navbar={{
        width: isTablet ? 60 : 230,
        breakpoint: 'md',
        collapsed: { mobile: !mobileOpened, desktop: !desktopOpened },
      }}
      padding="md"
    >
      <AppShell.Header>
        <Topbar 
          mobileOpened={mobileOpened} 
          desktopOpened={desktopOpened} 
          onMobileToggle={toggleMobile} 
          onDesktopToggle={toggleDesktop} 
        />
      </AppShell.Header>

      <AppShell.Navbar>
        <Sidebar collapsed={isTablet && !isMobile} onNavClick={() => isMobile && toggleMobile()} />
      </AppShell.Navbar>

      <AppShell.Main>
        {children}
      </AppShell.Main>
    </AppShell>
  )
}
