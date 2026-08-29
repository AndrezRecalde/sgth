'use client'

import { DashboardSsoTab } from '@/features/sso/components/DashboardSsoTab'
import { PageHeader, PageShell } from '@/components/ui'

export default function RiesgosLaboralesIndexPage() {
  return (
    <PageShell>
      <PageHeader
        title="Riesgos Laborales (SSO)"
        description="Dashboard consolidado — GAD Provincial de Esmeraldas"
      />
      <DashboardSsoTab />
    </PageShell>
  )
}
