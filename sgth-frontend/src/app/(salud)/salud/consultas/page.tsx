import type { Metadata } from 'next'
import { ConsultasTurnosView } from '@/features/dispensario/components/ConsultasTurnosView'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Consultas',
  description: 'Mis pacientes del día',
}

export default function ConsultasPage() {
  return (
    <PageShell>
      <PageHeader
        title="Consultas"
        description="Mis pacientes del día"
      />
      <ConsultasTurnosView />
    </PageShell>
  )
}
