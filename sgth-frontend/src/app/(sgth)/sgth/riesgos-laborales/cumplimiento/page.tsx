'use client'

import { CumplimientoTab } from '@/features/sso/components/CumplimientoTab'
import { PageHeader, PageShell } from '@/components/ui'

export default function CumplimientoNormativoPage() {
  return (
    <PageShell>
      <PageHeader
        title="Cumplimiento Normativo"
        description="Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas"
      />
      <CumplimientoTab />
    </PageShell>
  )
}
