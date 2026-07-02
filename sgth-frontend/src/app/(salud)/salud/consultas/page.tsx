'use client'

import { useState } from 'react'
import {
  Stack, Card, Alert, Text, Button, Group,
} from '@mantine/core'
import {
  IconStethoscope, IconCheck, IconArrowRight,
} from '@tabler/icons-react'
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
  const [paso, setPaso]             = useState<Paso>('lista')
  const [turno, setTurno]           = useState<AgendaMedica | null>(null)
  const [consultaCreada, setConsultaCreada] =
    useState<ConsultaMedica | null>(null)

  const handleReiniciar = () => {
    setPaso('lista')
    setTurno(null)
    setConsultaCreada(null)
  }

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
        <>
          {consultaCreada && (
            <Alert
              icon={<IconCheck size={14} />}
              color="emerald"
              variant="light"
            >
              <Group justify="space-between" wrap="nowrap">
                <Text size="sm" fw={600}>
                  Consulta guardada — puede recetar o emitir
                  un certificado antes de finalizar.
                </Text>
                <Button
                  size="xs"
                  variant="light"
                  color="emerald"
                  rightSection={<IconArrowRight size={13} />}
                  onClick={handleReiniciar}
                >
                  Finalizar y atender otro
                </Button>
              </Group>
            </Alert>
          )}

          <ConsultaMedicaForm
            turno={turno}
            historiaClinicaId={turno.historia_clinica_id}
            onGuardada={(consulta) => {
              setConsultaCreada(consulta)
            }}
            onCancelar={handleReiniciar}
          />
        </>
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
