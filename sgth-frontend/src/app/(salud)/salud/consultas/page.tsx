'use client'

import { useState } from 'react'
import { Stack, Card, Alert, Text } from '@mantine/core'
import { IconStethoscope, IconCheck } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { PacientesListosTable } from
  '@/features/dispensario/components/PacientesListosTable'
import { ConsultaMedicaForm } from
  '@/features/dispensario/components/ConsultaMedicaForm'
import type { AgendaMedica } from
  '@/features/dispensario/services/agendaService'
import type { ConsultaMedica } from
  '@/features/dispensario/services/consultaMedicaService'

type Paso = 'lista' | 'consulta' | 'finalizado'

export default function ConsultasPage() {
  const [paso, setPaso] = useState<Paso>('lista')
  const [turno, setTurno] = useState<AgendaMedica | null>(null)
  const [consultaCreada, setConsultaCreada] =
    useState<ConsultaMedica | null>(null)

  const handleReiniciar = () => {
    setPaso('lista')
    setTurno(null)
    setConsultaCreada(null)
  }

  // Se necesita el historia_clinica_id del paciente.
  // Se resuelve a partir del turno cuando se "Atiende".
  const handleAtender = (t: AgendaMedica) => {
    setTurno(t)
    setPaso('consulta')
  }

  return (
    <Stack gap="md">
      <PageHeader
        title="Consultas"
        subtitle="Pacientes listos para atención médica"
        icon={<IconStethoscope size={24} />}
      />

      {paso === 'lista' && (
        <PacientesListosTable onAtender={handleAtender} />
      )}

      {paso === 'consulta' && turno && turno.historia_clinica_id && (
        <ConsultaMedicaForm
          turno={turno}
          historiaClinicaId={turno.historia_clinica_id}
          onGuardada={(consulta) => {
            setConsultaCreada(consulta)
            setPaso('finalizado')
          }}
          onCancelar={handleReiniciar}
        />
      )}

      {paso === 'finalizado' && (
        <Card withBorder radius="lg" p="lg">
          <Stack gap="md" align="center">
            <Alert
              icon={<IconCheck size={16} />}
              color="emerald"
              variant="light"
              w="100%"
            >
              <Text size="sm" fw={600} ta="center">
                Consulta registrada exitosamente
              </Text>
            </Alert>
            <Card.Section
              p="sm"
              onClick={handleReiniciar}
              style={{ cursor: 'pointer', textAlign: 'center' }}
            >
              <Text size="sm" c="emerald" fw={600}>
                Atender otro paciente
              </Text>
            </Card.Section>
          </Stack>
        </Card>
      )}
    </Stack>
  )
}
