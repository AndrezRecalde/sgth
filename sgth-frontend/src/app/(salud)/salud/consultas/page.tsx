'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { IconStethoscope } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { PageHeader } from '@/components/ui/PageHeader'
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
    <Stack gap="md">
      <PageHeader
        title="Consultas"
        subtitle="Mis pacientes del día"
        icon={<IconStethoscope size={24} />}
      />
      <TurnosDelDiaTable
        onAtender={handleAtender}
        onVerConsulta={handleVerConsulta}
      />
    </Stack>
  )
}
