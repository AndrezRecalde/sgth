'use client'

import { EnfermeriaAtenderPacienteView } from '@/features/dispensario/components/EnfermeriaAtenderPacienteView'
import { PageHeader, PageShell } from '@/components/ui'

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
