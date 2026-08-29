'use client'

import { AppShell, Center, Loader } from '@mantine/core'
import { useDisclosure, useMediaQuery } from '@mantine/hooks'
import { usePathname, useRouter } from 'next/navigation'
import { useEffect } from 'react'
import { Sidebar } from './Sidebar'
import { Topbar } from './Topbar'
import { CommandPalette } from './CommandPalette'
import { useAuth } from '@/hooks/useAuth'
import { useHydrated } from '@/hooks/useHydrated'
import { useUiStore } from '@/store/ui.store'
import { getSubsistema } from '@/config/subsistemas'
import { ROUTES } from '@/config/routes'
import { LAYOUT } from '@/config/design.tokens'

/**
 * Shell de la aplicación autenticada. Lo montan los layouts de los tres
 * route groups protegidos: (sgth), (salud) y (portal).
 *
 * Responsabilidades — y solo estas tres:
 *  1. Guardar la ruta: sin sesión rehidratada, a /login.
 *  2. Publicar el subsistema activo como `data-subsistema`, de donde cuelga
 *     todo el acento visual (ver `styles/tokens.css`).
 *  3. Coordinar el estado abierto/plegado del sidebar entre Topbar y Sidebar.
 *
 * Comportamiento responsive:
 *   ≥ md  → sidebar fijo; el usuario puede plegarlo a iconos (se recuerda).
 *   < md  → sidebar oculto; el burger lo abre como capa sobre el contenido.
 *
 * El padding de página vive aquí, en el prop `padding`, y no en cada pantalla:
 * ponerlo en el contenedor de página obligaría a que las 51 pantallas se
 * acuerden de usarlo, y la que se olvide queda pegada al borde.
 *
 * Va por el prop y NO por una regla CSS propia sobre `AppShell.Main`. Mantine
 * suma ese valor a los offsets del header y el navbar fijos dentro de un mismo
 * `calc()`; una regla con el shorthand `padding` pisa las cuatro longhands,
 * offsets incluidos, y el contenido termina por debajo de ambos.
 */
export function SGTHAppShell({ children }: { children: React.ReactNode }) {
  const [mobileOpened, mobile] = useDisclosure(false)
  const { navbarCollapsed, toggleNavbar } = useUiStore()

  const isDesktop = useMediaQuery('(min-width: 62em)')
  const pathname = usePathname()
  const router = useRouter()
  const { token, isAuthenticated } = useAuth()
  const hydrated = useHydrated()

  const subsistema = getSubsistema(pathname)
  const sinSesion = hydrated && (!isAuthenticated || !token)

  useEffect(() => {
    if (sinSesion) router.replace(ROUTES.AUTH.LOGIN)
  }, [sinSesion, router])

  // Cerrar el sidebar móvil al navegar: en móvil tapa el contenido.
  useEffect(() => {
    mobile.close()
    // `mobile` es estable entre renders (useDisclosure); solo la ruta dispara esto.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pathname])

  // Mientras no sepamos si hay sesión, nada de contenido ni de redirección:
  // pintar el shell y quitarlo produce un parpadeo en cada carga.
  if (!hydrated || !isAuthenticated || !token) {
    return (
      <Center h="100dvh" bg="var(--sgth-canvas)">
        <Loader color="emerald" size="lg" type="dots" />
      </Center>
    )
  }

  return (
    <AppShell
      data-subsistema={subsistema}
      layout="alt"
      header={{ height: LAYOUT.headerHeight }}
      navbar={{
        width: navbarCollapsed && isDesktop
          ? LAYOUT.navbarWidthCollapsed
          : LAYOUT.navbarWidth,
        breakpoint: 'md',
        collapsed: { mobile: !mobileOpened },
      }}
      padding={{ base: 'md', sm: 'lg' }}
      transitionDuration={180}
      transitionTimingFunction="ease"
    >
      <AppShell.Header withBorder={false}>
        <Topbar
          subsistema={subsistema}
          mobileOpened={mobileOpened}
          navbarCollapsed={navbarCollapsed}
          onMobileToggle={mobile.toggle}
          onNavbarToggle={toggleNavbar}
        />
      </AppShell.Header>

      <AppShell.Navbar withBorder={false}>
        <Sidebar
          subsistema={subsistema}
          collapsed={navbarCollapsed && isDesktop}
          onNavigate={mobile.close}
        />
      </AppShell.Navbar>

      <AppShell.Main>{children}</AppShell.Main>

      <CommandPalette subsistema={subsistema} />
    </AppShell>
  )
}
