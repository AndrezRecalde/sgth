"use client";

import { AppShell, Center, Loader } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { Sidebar } from "./Sidebar";
import { Topbar } from "./Topbar";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useAuth } from "@/hooks/useAuth";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

export function SGTHAppShell({ children }: { children: React.ReactNode }) {
  const [mobileOpened, { toggle: toggleMobile }] = useDisclosure(false);
  const [desktopOpened, { toggle: toggleDesktop }] = useDisclosure(true);
  const { isTablet, isMobile } = useMobileBreakpoint();
  const { token, isAuthenticated } = useAuth();
  const router = useRouter();
  const [isHydrated, setIsHydrated] = useState(false);

  useEffect(() => {
    // Indicar que ya cargó en el cliente y Zustand se ha rehidratado de forma asíncrona
    const timer = setTimeout(() => {
      setIsHydrated(true);
    }, 0);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    if (isHydrated && (!isAuthenticated || !token)) {
      router.replace('/login')
    }
  }, [isHydrated, isAuthenticated, token, router])

  // Mostrar un loader limpio de pantalla completa si no está hidratado o no está autenticado
  if (!isHydrated || !isAuthenticated || !token) {
    return (
      <Center
        style={{
          width: "100vw",
          height: "100vh",
          background: "var(--mantine-color-body)",
        }}
      >
        <Loader color="emerald" size="xl" type="dots" />
      </Center>
    );
  }

  return (
    <AppShell
      layout="alt"
      header={{ height: 70 }}
      navbar={{
        width: isTablet ? 60 : 230,
        breakpoint: "md",
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
        <Sidebar
          collapsed={isTablet && !isMobile}
          onNavClick={() => isMobile && toggleMobile()}
        />
      </AppShell.Navbar>

      <AppShell.Main>{children}</AppShell.Main>
    </AppShell>
  );
}
