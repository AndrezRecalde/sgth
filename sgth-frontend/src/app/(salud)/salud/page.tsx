import type { Metadata } from 'next'
import { Stack } from '@mantine/core'
import { DisponibilidadToggle } from '@/features/dispensario/components/DisponibilidadToggle'
import { TableroDispensario } from '@/features/dispensario/components/TableroDispensario'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Dispensario Médico',
  description: 'Sistema de Salud Ambulatoria — GADPE',
}

export default function SaludHomePage() {
  return (
    <PageShell>
      <PageHeader
        title="Dispensario Médico"
        description="Sistema de Salud Ambulatoria — GADPE"
      />
      <Stack gap="lg">
        <DisponibilidadToggle />
        <TableroDispensario />
      </Stack>
    </PageShell>
  )
}
