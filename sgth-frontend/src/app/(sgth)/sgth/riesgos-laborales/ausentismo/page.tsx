'use client'

import { AusentismoTab } from '@/features/sso/components/AusentismoTab'
import { PageHeader, PageShell } from '@/components/ui'

export default function AusentismoPage() {
  return (
    <PageShell>
      <PageHeader
        title="Ausentismo por Enfermedad"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <AusentismoTab />
    </PageShell>
  )
}
