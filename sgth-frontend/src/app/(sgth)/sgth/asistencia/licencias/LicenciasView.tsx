'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { LicenciasTab } from '@/features/asistencia/components/LicenciasTab'

export function LicenciasView() {
  return (
    <PageShell>
      <PageHeader
        title="Licencias"
        description="Licencias con remuneración: registro, aprobación y registro en Sirha7. No descuentan vacaciones."
      />
      <LicenciasTab />
    </PageShell>
  )
}
