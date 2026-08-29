'use client'

import { ProgramaDrogasTab } from '@/features/sso/components/ProgramaDrogasTab'
import { PageHeader, PageShell } from '@/components/ui'

export default function ProgramaDrogasPage() {
  return (
    <PageShell>
      <PageHeader
        title="Programa de Prevención de Drogas"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <ProgramaDrogasTab />
    </PageShell>
  )
}
