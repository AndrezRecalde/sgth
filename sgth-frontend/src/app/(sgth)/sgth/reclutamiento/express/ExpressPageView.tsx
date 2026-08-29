'use client'

import { ReclutamientoExpressView } from '@/features/seleccion/components/ReclutamientoExpressView'
import { PageHeader, PageShell } from '@/components/ui'

export function ExpressPageView() {
  return (
    <PageShell>
      <PageHeader
        title="Reclutamiento Express"
        description="Provisionales, ocasionales, servicios profesionales y Código del Trabajo"
      />

      <ReclutamientoExpressView />
    </PageShell>
  )
}
