import type { Metadata } from 'next'
import { IndicadoresSsoTab } from '@/features/sso/components/IndicadoresSsoTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Indicadores SSO',
  description: 'Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas',
}

export default function IndicadoresSsoPage() {
  return (
    <PageShell>
      <PageHeader
        title="Indicadores SSO"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <IndicadoresSsoTab />
    </PageShell>
  )
}
