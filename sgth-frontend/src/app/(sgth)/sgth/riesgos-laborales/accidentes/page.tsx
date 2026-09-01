import type { Metadata } from 'next'
import { AccidentesTrabajoTab } from '@/features/sso/components/AccidentesTrabajoTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Accidentes de Trabajo',
  description: 'Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas',
}

export default function AccidentesTrabajoPage() {
  return (
    <PageShell>
      <PageHeader
        title="Accidentes de Trabajo"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <AccidentesTrabajoTab />
    </PageShell>
  )
}
