"use client";

import { AppShell } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { Topbar } from "@/components/layout/Topbar";

export default function WelcomeLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const [mobileOpened, { toggle: toggleMobile }] = useDisclosure(false);
  const [desktopOpened, { toggle: toggleDesktop }] = useDisclosure(false);

  return (
    <AppShell header={{ height: 70 }} padding="md">
      <AppShell.Header>
        <Topbar
          mobileOpened={mobileOpened}
          desktopOpened={desktopOpened}
          onMobileToggle={toggleMobile}
          onDesktopToggle={toggleDesktop}
        />
      </AppShell.Header>
      <AppShell.Main>{children}</AppShell.Main>
    </AppShell>
  );
}
