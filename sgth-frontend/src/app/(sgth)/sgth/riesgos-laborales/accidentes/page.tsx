'use client'

import { AccidentesTrabajoTab } from '@/features/sso/components/AccidentesTrabajoTab'
import { PageHeader, PageShell } from '@/components/ui'

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
