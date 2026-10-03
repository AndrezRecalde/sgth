import type { Metadata } from 'next'
import { PageHeader, PageShell } from '@/components/ui'
import { ReportesDispensarioView } from '@/features/dispensario/components/reportes/ReportesDispensarioView'

export const metadata: Metadata = {
  title: 'Reportes del Dispensario',
  description: 'Atención, enfermería, farmacia, salud ocupacional y gestión del Dispensario',
}

export default function ReportesDispensarioPage() {
  return (
    <PageShell>
      <PageHeader
        title="Reportes"
        description="Atención, enfermería, farmacia, salud ocupacional y gestión del Dispensario"
      />
      <ReportesDispensarioView />
    </PageShell>
  )
}
