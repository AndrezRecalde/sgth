'use client'

import { useState } from 'react'
import {
  Stack, Card, Alert, Text, Tabs,
  Select, Group,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import {
  IconNurse, IconCheck, IconList, IconUserPlus,
} from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { BuscarPacienteForm } from
  '@/features/dispensario/components/BuscarPacienteForm'
import { CrearTurnoForm } from
  '@/features/dispensario/components/CrearTurnoForm'
import { ColaTurnosTable } from
  '@/features/dispensario/components/ColaTurnosTable'
import { useColaTurnos, useCancelarTurno } from
  '@/features/dispensario/hooks/useAgenda'
import type { PacienteEncontrado } from
  '@/features/dispensario/services/pacienteService'
import type { AgendaMedica } from
  '@/features/dispensario/services/agendaService'

type Paso = 'buscar' | 'turno' | 'creado'

function NuevaAdmision() {
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
                Turno {agendaCreada.folio} creado
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

function formatFechaLocal(d: Date): string {
  const year  = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day   = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function ColaDelDia() {
  const [fecha, setFecha] = useState<Date | null>(new Date())

  const fechaStr = formatFechaLocal(fecha ?? new Date())

  const { data, isLoading } = useColaTurnos({ fecha: fechaStr })
  const cancelar = useCancelarTurno()

  const turnos = data?.data ?? []

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <DatePickerInput
          label="Fecha"
          value={fecha}
          onChange={(v) => {
            if (!v) {
              setFecha(new Date())
              return
            }
            // Evita desfase de zona horaria: construye
            // la fecha local a partir de los componentes
            // año/mes/día del string recibido (YYYY-MM-DD)
            const str = typeof v === 'string' ? v : String(v)
            const [y, m, d] = str.slice(0, 10).split('-').map(Number)
            setFecha(new Date(y, m - 1, d))
          }}
          valueFormat="DD/MM/YYYY"
          maw={200}
        />
      </Group>

      <ColaTurnosTable
        turnos={turnos}
        isLoading={isLoading}
        onCancelar={(id) => {
          if (confirm('¿Cancelar este turno?')) {
            cancelar.mutate(id)
          }
        }}
      />
    </Stack>
  )
}

export default function EnfermeriaPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Enfermería"
        subtitle="Admisión de pacientes y cola de turnos"
        icon={<IconNurse size={24} />}
      />

      <Tabs defaultValue="admision">
        <Tabs.List>
          <Tabs.Tab
            value="admision"
            leftSection={<IconUserPlus size={14} />}
          >
            Nueva admisión
          </Tabs.Tab>
          <Tabs.Tab
            value="cola"
            leftSection={<IconList size={14} />}
          >
            Cola del día
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="admision" pt="md">
          <NuevaAdmision />
        </Tabs.Panel>

        <Tabs.Panel value="cola" pt="md">
          <ColaDelDia />
        </Tabs.Panel>
      </Tabs>
    </Stack>
  )
}
