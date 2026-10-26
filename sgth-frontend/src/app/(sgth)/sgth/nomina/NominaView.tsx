'use client'

import { NovedadesNominaPanel } from '@/features/nomina/components/NovedadesNominaPanel'
import { PageHeader, PageShell } from '@/components/ui'

/**
 * Las novedades del mes (fase 7.3a). Desde el 2026-10-20 el SGTH es
 * informativo: no calcula la nómina, que Financiero paga en su sistema; le
 * dice lo que pasó en el mes, y de la nómina solo queda esto. La ve Talento
 * Humano; Financiero las recibe por correo al entregarse el mes.
 */
export function NominaView() {
  return (
    <PageShell>
      <PageHeader
        title="Novedades del mes"
        description="Lo que pasó en el mes. Al entregarlo, Financiero lo recibe por correo para su rol de pagos"
      />
      <NovedadesNominaPanel />
    </PageShell>
  )
}
