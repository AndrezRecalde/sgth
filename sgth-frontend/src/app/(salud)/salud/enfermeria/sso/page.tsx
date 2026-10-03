import type { Metadata } from 'next'
import { EnfermeriaSsoTriajeView } from '@/features/dispensario/components/EnfermeriaSsoTriajeView'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Atención SSO',
  description: 'Atención SSO — signos vitales previos al FEMO',
}

export default function EnfermeriaSsoPage() {
  return (
    <PageShell>
      <PageHeader
        title="Atención SSO"
        description="Signos vitales previos a la evaluación médica ocupacional"
      />
      <EnfermeriaSsoTriajeView />
    </PageShell>
  )
}
