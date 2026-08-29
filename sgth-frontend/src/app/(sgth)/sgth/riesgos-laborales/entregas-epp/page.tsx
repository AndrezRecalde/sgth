'use client'

import { EntregasEppTab } from '@/features/sso/components/EntregasEppTab'
import { PageHeader, PageShell } from '@/components/ui'

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
