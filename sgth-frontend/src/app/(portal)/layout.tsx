import { SGTHAppShell } from '@/components/layout/AppShell'

export default function PortalLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return <SGTHAppShell>{children}</SGTHAppShell>
}
