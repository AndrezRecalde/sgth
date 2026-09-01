import type { Metadata } from 'next'
import { RiesgosLaboralesTab } from '@/features/sso/components/RiesgosLaboralesTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Factores de Riesgo Laboral',
  description: 'Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas',
}

export default function FactoresRiesgoPage() {
  return (
    <PageShell>
      <PageHeader
        title="Factores de Riesgo Laboral"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <RiesgosLaboralesTab />
    </PageShell>
  )
}
