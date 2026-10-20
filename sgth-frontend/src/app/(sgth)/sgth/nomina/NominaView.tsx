'use client'

import { NovedadesNominaPanel } from '@/features/nomina/components/NovedadesNominaPanel'
import { PageHeader, PageShell } from '@/components/ui'

/**
 * Las novedades del mes para Financiero (fase 7.3a). Desde el 2026-10-20 el
 * SGTH es informativo: no calcula la nómina, que Financiero paga en su
 * sistema; le dice lo que pasó en el mes, y de la nómina solo queda esto.
 */
export function NominaView() {
  return (
    <PageShell>
      <PageHeader
        title="Novedades para Financiero"
        description="Lo que pasó en el mes y Financiero aplica en su rol de pagos"
      />
      <NovedadesNominaPanel />
    </PageShell>
  )
}
