import { SGTHAppShell } from '@/components/layout/AppShell'

export default function SgthLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return <SGTHAppShell>{children}</SGTHAppShell>
}
