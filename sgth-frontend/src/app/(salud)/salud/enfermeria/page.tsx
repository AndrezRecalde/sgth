import type { Metadata } from 'next'
import { EnfermeriaAtenderPacienteView } from '@/features/dispensario/components/EnfermeriaAtenderPacienteView'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Enfermería',
  description: 'Atender paciente — admisión y triaje',
}

export default function EnfermeriaPage() {
  return (
    <PageShell>
      <PageHeader
        title="Enfermería"
        description="Atender paciente — admisión y triaje"
      />
      <EnfermeriaAtenderPacienteView />
    </PageShell>
  )
}
