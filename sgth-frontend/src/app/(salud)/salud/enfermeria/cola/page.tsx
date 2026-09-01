import type { Metadata } from 'next'
import { EnfermeriaColaMonitoreoView } from '@/features/dispensario/components/EnfermeriaColaMonitoreoView'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  // Distinto del de /salud/enfermeria: si ambas dijeran «Enfermería», las dos
  // pestañas del navegador quedarían con el mismo nombre.
  title: 'Cola de enfermería',
  description: 'Cola y monitoreo de turnos del Dispensario Médico',
}

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
