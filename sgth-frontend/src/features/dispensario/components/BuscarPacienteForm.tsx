'use client'

import { useState } from 'react'
import {
  Stack, Group, TextInput, Button,
  Alert, Text, ActionIcon,
} from '@mantine/core'
import { IconSearch, IconX, IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useBuscarPaciente } from '../hooks/usePaciente'
import { useCrearHistoriaClinica } from '../hooks/useHistoriaClinica'
import { PacienteCard } from './PacienteCard'
import type { PacienteEncontrado } from '../services/pacienteService'

interface Props {
  onPacienteListo: (
    paciente: PacienteEncontrado,
    historiaClinicaId: number
  ) => void
}

export function BuscarPacienteForm({ onPacienteListo }: Props) {
  const contained = useContainedInput()
  const [cedula, setCedula] = useState('')

  const buscar = useBuscarPaciente()
  const crearHistoria = useCrearHistoriaClinica()

  const handleBuscar = () => {
    if (!cedula.trim()) return
    buscar.mutate(cedula.trim())
  }

  const handleCrearHistoria = async () => {
    const paciente = buscar.data
    if (!paciente) return

    const data = await crearHistoria.mutateAsync(
      paciente.tipo === 'servidor'
        ? { servidor_id: paciente.id }
        : { beneficiario_id: paciente.id }
    )

    onPacienteListo(paciente, data.id)
  }

  const handleContinuar = () => {
    const paciente = buscar.data
    if (!paciente || !paciente.historia_clinica_id) return
    onPacienteListo(paciente, paciente.historia_clinica_id)
  }

  return (
    <Stack gap="md">
      <Group gap="xs" align="flex-end">
        <TextInput
          label="Cédula del paciente"
          placeholder="Ej: 0801234567"
          leftSection={<IconSearch size={14} />}
          {...contained}
          value={cedula}
          onChange={(e) => setCedula(e.currentTarget.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              handleBuscar()
            }
          }}
          style={{ flex: 1 }}
          rightSection={
            cedula ? (
              <ActionIcon
                size="sm" variant="subtle" color="gray"
                onClick={() => {
                  setCedula('')
                  buscar.reset()
                }}
              >
                <IconX size={12} />
              </ActionIcon>
            ) : null
          }
        />
        <Button
          color="blue"
          leftSection={<IconSearch size={14} />}
          loading={buscar.isPending}
          onClick={handleBuscar}
        >
          Buscar
        </Button>
      </Group>

      {buscar.isError && (
        <Alert
          icon={<IconInfoCircle size={14} />}
          color="red"
          variant="light"
        >
          <Text size="xs">
            No se encontró ningún servidor o familiar
            registrado con esa cédula.
          </Text>
        </Alert>
      )}

      {buscar.data && (
        <PacienteCard
          paciente={buscar.data}
          onCrearHistoria={handleCrearHistoria}
          onContinuar={handleContinuar}
          creandoHistoria={crearHistoria.isPending}
        />
      )}
    </Stack>
  )
}
