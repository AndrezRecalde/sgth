'use client'

import { RiesgosLaboralesTab } from '@/features/sso/components/RiesgosLaboralesTab'
import { PageHeader, PageShell } from '@/components/ui'

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
