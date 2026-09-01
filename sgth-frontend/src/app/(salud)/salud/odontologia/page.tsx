import type { Metadata } from 'next'
import { OdontologiaTurnosView } from '@/features/dispensario/components/OdontologiaTurnosView'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Odontología',
  description: 'Mis pacientes del día',
}

export default function OdontologiaPage() {
  return (
    <PageShell>
      <PageHeader
        title="Odontología"
        description="Mis pacientes del día"
      />
      <OdontologiaTurnosView />
    </PageShell>
  )
}
