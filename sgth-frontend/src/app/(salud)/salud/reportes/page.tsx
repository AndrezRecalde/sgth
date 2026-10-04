import type { Metadata } from 'next'
import { PageHeader, PageShell } from '@/components/ui'
import { ReportesDispensarioView } from '@/features/dispensario/components/reportes/ReportesDispensarioView'

export const metadata: Metadata = {
  title: 'Reportes del Dispensario',
  description: 'Atenciones, morbilidad y producción del Dispensario Médico',
}

export default function ReportesDispensarioPage() {
  return (
    <PageShell>
      <PageHeader
        title="Reportes"
        description="Atenciones, morbilidad y producción del Dispensario Médico"
      />
      <ReportesDispensarioView />
    </PageShell>
  )
}
