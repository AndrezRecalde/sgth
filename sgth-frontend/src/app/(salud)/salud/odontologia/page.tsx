'use client'

import { useRouter } from 'next/navigation'
import { TurnosDelDiaTable } from
  '@/features/dispensario/components/TurnosDelDiaTable'
import type { AgendaMedica } from
  '@/features/dispensario/services/agendaService'

export default function OdontologiaPage() {
  const router = useRouter()

  const handleAtender = (turno: AgendaMedica) => {
    router.push(`/salud/odontologia/${turno.folio}`)
  }

  const handleVerConsulta = (turno: AgendaMedica) => {
    router.push(`/salud/odontologia/${turno.folio}`)
  }

  return (
    <PageShell>
      <PageHeader
        title="Odontología"
        description="Mis pacientes del día"
      />
      <TurnosDelDiaTable
        onAtender={handleAtender}
        onVerConsulta={handleVerConsulta}
      />
    </PageShell>
  )
}

import { PageHeader, PageShell } from '@/components/ui'