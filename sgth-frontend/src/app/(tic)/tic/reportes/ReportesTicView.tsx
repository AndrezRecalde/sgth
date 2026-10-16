'use client'

import { useState } from 'react'
import { Stack, Tabs } from '@mantine/core'
import { IconBuilding, IconDroplet, IconHourglassLow, IconLifebuoy, IconMoodSmile, IconStack2 } from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { ReporteConsumosPanel } from '@/features/reportes-tic/components/ReporteConsumosPanel'
import { ReporteInventarioPanel } from '@/features/reportes-tic/components/ReporteInventarioPanel'
import { ReporteObsolescenciaPanel } from '@/features/reportes-tic/components/ReporteObsolescenciaPanel'
import { ReporteSaldoConsumiblesPanel } from '@/features/reportes-tic/components/ReporteSaldoConsumiblesPanel'
import { ReporteSatisfaccionPanel } from '@/features/reportes-tic/components/ReporteSatisfaccionPanel'
import { ReporteTicketsPanel } from '@/features/reportes-tic/components/ReporteTicketsPanel'

/**
 * Los reportes de Tecnologías (fase 4.2), en pantalla y en Excel o PDF. Los
 * del inventario, para quien lo consulta; los de la mesa de ayuda, para quien
 * la ve entera. El uso de los equipos de préstamo tiene su propia pantalla.
 */
export function ReportesTicView() {
  const { hasPermiso } = useAuth()
  const inventario = hasPermiso('ver-inventario-ti') || hasPermiso('gestionar-inventario-ti')
  const mesa = hasPermiso('ver-tickets-todos')
  const [pestana, setPestana] = useState<string | null>(inventario ? 'inventario' : 'tickets')

  return (
    <PageShell>
      <PageHeader title="Reportes" description="Inventario, obsolescencia, consumibles y mesa de ayuda; en pantalla, Excel o PDF" />
      <Tabs value={pestana} onChange={setPestana} keepMounted={false}>
        <Tabs.List>
          {inventario && (
            <>
              <Tabs.Tab value="inventario" leftSection={<IconBuilding size={14} />}>Inventario</Tabs.Tab>
              <Tabs.Tab value="obsolescencia" leftSection={<IconHourglassLow size={14} />}>Obsolescencia</Tabs.Tab>
              <Tabs.Tab value="consumos" leftSection={<IconDroplet size={14} />}>Consumo</Tabs.Tab>
              <Tabs.Tab value="saldo-consumibles" leftSection={<IconStack2 size={14} />}>Saldo de consumibles</Tabs.Tab>
            </>
          )}
          {mesa && (
            <>
              <Tabs.Tab value="tickets" leftSection={<IconLifebuoy size={14} />}>Mesa de ayuda</Tabs.Tab>
              <Tabs.Tab value="satisfaccion" leftSection={<IconMoodSmile size={14} />}>Satisfacción</Tabs.Tab>
            </>
          )}
        </Tabs.List>
        {inventario && (
          <>
            <Tabs.Panel value="inventario" pt="lg"><Stack gap="md"><ReporteInventarioPanel /></Stack></Tabs.Panel>
            <Tabs.Panel value="obsolescencia" pt="lg"><Stack gap="md"><ReporteObsolescenciaPanel /></Stack></Tabs.Panel>
            <Tabs.Panel value="consumos" pt="lg"><Stack gap="md"><ReporteConsumosPanel /></Stack></Tabs.Panel>
            <Tabs.Panel value="saldo-consumibles" pt="lg"><Stack gap="md"><ReporteSaldoConsumiblesPanel /></Stack></Tabs.Panel>
          </>
        )}
        {mesa && (
          <>
            <Tabs.Panel value="tickets" pt="lg"><Stack gap="md"><ReporteTicketsPanel /></Stack></Tabs.Panel>
            <Tabs.Panel value="satisfaccion" pt="lg"><Stack gap="md"><ReporteSatisfaccionPanel /></Stack></Tabs.Panel>
          </>
        )}
      </Tabs>
    </PageShell>
  )
}
