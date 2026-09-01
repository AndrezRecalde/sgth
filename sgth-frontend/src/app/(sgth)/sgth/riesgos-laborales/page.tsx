import type { Metadata } from 'next'
import { DashboardSsoTab } from '@/features/sso/components/DashboardSsoTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Riesgos Laborales (SSO)',
  description: 'Dashboard consolidado — GAD Provincial de Esmeraldas',
}

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
