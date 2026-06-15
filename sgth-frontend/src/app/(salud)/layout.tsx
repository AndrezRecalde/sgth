import { SGTHAppShell } from '@/components/layout/AppShell'

export default function SaludLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return <SGTHAppShell>{children}</SGTHAppShell>
}
