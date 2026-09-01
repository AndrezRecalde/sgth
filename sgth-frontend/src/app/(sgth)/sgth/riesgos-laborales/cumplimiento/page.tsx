import type { Metadata } from 'next'
import { CumplimientoTab } from '@/features/sso/components/CumplimientoTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Cumplimiento Normativo',
  description: 'Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas',
}

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
