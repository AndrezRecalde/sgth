import type { Metadata } from 'next'
import { CampaniasPsicosocialTab } from '@/features/sso/components/CampaniasPsicosocialTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Evaluación Psicosocial',
  description: 'Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas',
}

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
