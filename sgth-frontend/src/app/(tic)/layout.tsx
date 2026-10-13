import { SGTHAppShell } from '@/components/layout/AppShell'

export default function TicLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return <SGTHAppShell>{children}</SGTHAppShell>
}
