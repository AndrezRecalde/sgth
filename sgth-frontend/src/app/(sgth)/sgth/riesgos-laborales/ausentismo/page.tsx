import type { Metadata } from 'next'
import { AusentismoTab } from '@/features/sso/components/AusentismoTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Ausentismo por Enfermedad',
  description: 'Riesgos Laborales (SSO) — GAD Provincial de Esmeraldas',
}

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
