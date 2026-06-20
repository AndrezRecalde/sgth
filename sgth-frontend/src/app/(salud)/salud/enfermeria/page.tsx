'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { IconNurse } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { BuscarPacienteForm } from
  '@/features/dispensario/components/BuscarPacienteForm'
import type { PacienteEncontrado } from
  '@/features/dispensario/services/pacienteService'

export default function EnfermeriaPage() {
  const [, setPacienteActivo] = useState<{
    paciente:          PacienteEncontrado
    historiaClinicaId: number
  } | null>(null)

  return (
    <Stack gap="md">
      <PageHeader
        title="Enfermería — Admisión"
        subtitle="Busca al paciente para crear un turno"
        icon={<IconNurse size={24} />}
      />

      <BuscarPacienteForm
        onPacienteListo={(paciente, historiaClinicaId) => {
          setPacienteActivo({ paciente, historiaClinicaId })
          // Próximo paso: formulario de turno
        }}
      />
    </Stack>
  )
}
