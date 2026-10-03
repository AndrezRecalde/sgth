import type { Metadata } from 'next'
import { OdontologiaTurnosView } from '@/features/dispensario/components/OdontologiaTurnosView'
import { PageHeader, PageShell } from '@/components/ui'
import { MiJornadaFranja } from '@/features/dispensario/components/MiJornadaFranja'

export const metadata: Metadata = {
  title: 'Odontología',
  description: 'Mis pacientes del día',
}

export default function OdontologiaPage() {
  return (
    <PageShell>
      <PageHeader
        title="Odontología"
        description="Mis pacientes del día"
      />
      <MiJornadaFranja contexto="odontologo" />
      <OdontologiaTurnosView />
    </PageShell>
  )
}
