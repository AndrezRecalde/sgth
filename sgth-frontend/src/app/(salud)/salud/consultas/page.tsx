'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { IconStethoscope } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { TurnosDelDiaTable } from
  '@/features/dispensario/components/TurnosDelDiaTable'
import { AtencionMedicaPanel } from
  '@/features/dispensario/components/AtencionMedicaPanel'
import { useTurnosDelDia } from
  '@/features/dispensario/hooks/useAgenda'
import type { AgendaMedica } from
  '@/features/dispensario/services/agendaService'

export default function ConsultasPage() {
  const [turnoActivo, setTurnoActivo] =
    useState<AgendaMedica | null>(null)

  const { data: turnos = [] } = useTurnosDelDia()

  const totalEnEspera = turnos.filter(t =>
    ['en_espera', 'en_sala'].includes(t.estado)
  ).length

  const handleAtender = (turno: AgendaMedica) => {
    setTurnoActivo(turno)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const handleVerConsulta = (turno: AgendaMedica) => {
    setTurnoActivo(turno)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const handleFinalizar = () => {
    setTurnoActivo(null)
  }

  return (
    <Stack gap="md">
      {!turnoActivo && (
        <PageHeader
          title="Consultas"
          subtitle="Mis pacientes del día"
          icon={<IconStethoscope size={24} />}
        />
      )}

      {turnoActivo ? (
        <AtencionMedicaPanel
          turno={turnoActivo}
          historiaClinicaId={turnoActivo.historia_clinica_id ?? 0}
          totalEnEspera={totalEnEspera}
          onFinalizar={handleFinalizar}
        />
      ) : (
        <TurnosDelDiaTable
          onAtender={handleAtender}
          onVerConsulta={handleVerConsulta}
        />
      )}
    </Stack>
  )
}
