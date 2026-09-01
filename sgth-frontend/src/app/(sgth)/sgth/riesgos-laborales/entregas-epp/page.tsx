import type { Metadata } from 'next'
import { EntregasEppTab } from '@/features/sso/components/EntregasEppTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Entregas de EPP',
  description: 'Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas',
}

export default function EntregasEppPage() {
  return (
    <PageShell>
      <PageHeader
        title="Entregas de EPP"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <EntregasEppTab />
    </PageShell>
  )
}
