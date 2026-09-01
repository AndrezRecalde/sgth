'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { MarcacionesTab } from '@/features/asistencia/components/MarcacionesTab'

export function MarcacionesView() {
  return (
    <PageShell>
      <PageHeader
        title="Marcaciones"
        description="Registros del reloj biométrico por servidor y rango de fechas"
      />
      <MarcacionesTab />
    </PageShell>
  )
}
