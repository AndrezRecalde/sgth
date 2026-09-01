import type { Metadata } from 'next'
import { EnfermeriaSsoTriajeView } from '@/features/dispensario/components/EnfermeriaSsoTriajeView'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Enfermería SSO',
  description: 'Atención SSO — signos vitales previos al FEMO',
}

export default function EnfermeriaSsoPage() {
  return (
    <PageShell>
      <PageHeader
        title="Enfermería"
        description="Atención SSO — signos vitales previos al FEMO"
      />
      <EnfermeriaSsoTriajeView />
    </PageShell>
  )
}
