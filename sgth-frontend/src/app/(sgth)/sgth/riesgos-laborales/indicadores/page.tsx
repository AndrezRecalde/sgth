'use client'

import { IndicadoresSsoTab } from '@/features/sso/components/IndicadoresSsoTab'
import { PageHeader, PageShell } from '@/components/ui'

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
