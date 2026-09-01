import type { Metadata } from 'next'
import { ProgramaDrogasTab } from '@/features/sso/components/ProgramaDrogasTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Programa de Prevención de Drogas',
  description: 'Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas',
}

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
