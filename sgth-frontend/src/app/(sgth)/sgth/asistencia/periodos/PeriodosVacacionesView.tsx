'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { PeriodosVacacionesTab } from '@/features/asistencia/components/PeriodosVacacionesTab'

export function PeriodosVacacionesView() {
  return (
    <PageShell>
      <PageHeader
        title="Períodos de vacaciones"
        description="Generación de períodos, saldos por servidor y recálculo"
      />
      <PeriodosVacacionesTab />
    </PageShell>
  )
}
