'use client'

import { useRouter } from 'next/navigation'
import { TurnosDelDiaTable } from
  '@/features/dispensario/components/TurnosDelDiaTable'
import { useTurnosDelDia } from
  '@/features/dispensario/hooks/useAgenda'
import type { AgendaMedica } from
  '@/features/dispensario/services/agendaService'

export default function ConsultasPage() {
  const router = useRouter()
  const { data: turnos = [] } = useTurnosDelDia()

  const handleAtender = (turno: AgendaMedica) => {
    router.push(`/salud/consultas/${turno.folio}`)
  }

  const handleVerConsulta = (turno: AgendaMedica) => {
    router.push(`/salud/consultas/${turno.folio}`)
  }

  return (
    <PageShell>
      <PageHeader
        title="Consultas"
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