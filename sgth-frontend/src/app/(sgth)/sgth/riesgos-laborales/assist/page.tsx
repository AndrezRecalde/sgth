import type { Metadata } from 'next'
import { AssistCampaniasTab } from '@/features/sso/components/AssistCampaniasTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Tamizaje ASSIST',
  description: 'Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas',
}

export default function TamizajeAssistPage() {
  return (
    <PageShell>
      <PageHeader
        title="Tamizaje ASSIST"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <AssistCampaniasTab />
    </PageShell>
  )
}
