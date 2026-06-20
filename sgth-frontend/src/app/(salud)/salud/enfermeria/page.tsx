'use client'

import { useState } from 'react'
import { Stack, Card, Alert, Text } from '@mantine/core'
import { IconNurse, IconCheck } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { BuscarPacienteForm } from
  '@/features/dispensario/components/BuscarPacienteForm'
import { CrearTurnoForm } from
  '@/features/dispensario/components/CrearTurnoForm'
import type { PacienteEncontrado } from
  '@/features/dispensario/services/pacienteService'
import type { AgendaMedica } from
  '@/features/dispensario/services/agendaService'

type Paso = 'buscar' | 'turno' | 'creado'

export default function EnfermeriaPage() {
  const [paso, setPaso] = useState<Paso>('buscar')
  const [paciente, setPaciente] = useState<PacienteEncontrado | null>(null)
  const [agendaCreada, setAgendaCreada] = useState<AgendaMedica | null>(null)

  const handleReiniciar = () => {
    setPaso('buscar')
    setPaciente(null)
    setAgendaCreada(null)
  }

  return (
    <Stack gap="md" maw={600}>
      <PageHeader
        title="Enfermería — Admisión"
        subtitle="Busca al paciente para crear un turno"
        icon={<IconNurse size={24} />}
      />

      {paso === 'buscar' && (
        <BuscarPacienteForm
          onPacienteListo={(p) => {
            setPaciente(p)
            setPaso('turno')
          }}
        />
      )}

      {paso === 'turno' && paciente && (
        <CrearTurnoForm
          paciente={paciente}
          onCreado={(agenda) => {
            setAgendaCreada(agenda)
            setPaso('creado')
          }}
          onCancelar={handleReiniciar}
        />
      )}

      {paso === 'creado' && agendaCreada && (
        <Card withBorder radius="lg" p="lg">
          <Stack gap="md" align="center">
            <Alert
              icon={<IconCheck size={16} />}
              color="emerald"
              variant="light"
              w="100%"
            >
              <Text size="sm" fw={600}>
                Turno creado exitosamente
              </Text>
              <Text size="xs" mt={4}>
                El paciente puede dirigirse a triaje o
                directamente a consulta según corresponda.
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
