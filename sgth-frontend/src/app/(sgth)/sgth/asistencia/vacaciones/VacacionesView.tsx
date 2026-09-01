'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { VacacionesTab } from '@/features/asistencia/components/VacacionesTab'

export function VacacionesView() {
  return (
    <PageShell>
      <PageHeader
        title="Vacaciones"
        description="Solicitudes de vacaciones: registro, aprobación y anulación"
      />
      <VacacionesTab />
    </PageShell>
  )
}
