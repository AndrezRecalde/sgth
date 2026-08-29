'use client'

import { CampaniasPsicosocialTab } from '@/features/sso/components/CampaniasPsicosocialTab'
import { PageHeader, PageShell } from '@/components/ui'

export default function EvaluacionPsicosocialPage() {
  return (
    <PageShell>
      <PageHeader
        title="Evaluación Psicosocial"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <CampaniasPsicosocialTab />
    </PageShell>
  )
}
