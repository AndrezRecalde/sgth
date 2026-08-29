'use client'

import { EquiposProteccionTab } from '@/features/sso/components/EquiposProteccionTab'
import { PageHeader, PageShell } from '@/components/ui'

export default function EquiposProteccionPage() {
  return (
    <PageShell>
      <PageHeader
        title="Equipos de Protección Personal"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <EquiposProteccionTab />
    </PageShell>
  )
}
