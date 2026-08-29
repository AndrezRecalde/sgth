'use client'

import { EnfermeriaColaMonitoreoView } from '@/features/dispensario/components/EnfermeriaColaMonitoreoView'
import { PageHeader, PageShell } from '@/components/ui'

export default function EnfermeriaColaPage() {
  return (
    <PageShell>
      <PageHeader
        title="Enfermería"
        description="Cola y monitoreo de turnos"
      />
      <EnfermeriaColaMonitoreoView />
    </PageShell>
  )
}
