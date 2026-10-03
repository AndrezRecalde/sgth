'use client'

import { useState } from 'react'
import { Stack, TextInput, Button, Alert, Text, ActionIcon, Group } from '@mantine/core'
import { IconSearch, IconX, IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { getApiErrorMessage } from '@/types/api'
import { hoyIso } from '@/lib/fecha'
import { useBuscarPaciente } from '../hooks/usePaciente'
import { useCrearHistoriaClinica } from '../hooks/useHistoriaClinica'
import { useColaTurnos } from '../hooks/useAgenda'
import { TURNO_PENDIENTE } from '../constants/turnos'
import { PacienteCard, type AccionPaciente } from './PacienteCard'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  onElegir:       (paciente: PacienteEncontrado, accion: AccionPaciente) => void
  onTomarTriaje?: (turno: AgendaMedica) => void
}

/** Abierto hoy: esperando, listo para pasar o ya con el profesional. */
const ABIERTOS = [...TURNO_PENDIENTE, 'en_consulta']

/**
 * Buscar al paciente por cédula.
 *
 * Era una tarjeta con un icono de 56 px, título y descripción alrededor de un
 * solo campo; ahora es el campo con su botón al lado, y el cursor ya está en
 * él al entrar y al volver de atender a alguien: no hace falta tocar el ratón
 * para empezar con el siguiente.
 */
export function BuscarPacienteForm({ onElegir, onTomarTriaje }: Props) {
  const contained = useContainedInput()
  const [cedula, setCedula] = useState('')
  // La historia recién creada, para no tener que volver a buscar.
  const [historiaCreada, setHistoriaCreada] = useState<number | null>(null)

  const buscar = useBuscarPaciente()
  const crearHistoria = useCrearHistoriaClinica()
  const { data: cola } = useColaTurnos({ fecha: hoyIso(), per_page: 200 })

  const encontrado = buscar.data
  const paciente = encontrado && historiaCreada
    ? { ...encontrado, tiene_historia_clinica: true, historia_clinica_id: historiaCreada }
    : encontrado

  const turnoAbierto = paciente && cola?.data.find((t) =>
    ABIERTOS.includes(t.estado) && (paciente.tipo === 'servidor'
      ? t.servidor_id === paciente.id
      : t.carga_familiar_id === paciente.id),
  )

  const limpiar = () => {
    buscar.reset()
    setHistoriaCreada(null)
  }

  const handleBuscar = () => {
    if (!cedula.trim()) return
    setHistoriaCreada(null)
    buscar.mutate(cedula.trim())
  }

  const handleCrearHistoria = () => {
    if (!encontrado) return
    crearHistoria.mutate(
      encontrado.tipo === 'servidor'
        ? { servidor_id: encontrado.id }
        : { carga_familiar_id: encontrado.id },
      { onSuccess: (historia) => setHistoriaCreada(historia.id) },
    )
  }

  return (
    <Stack gap="md">
      <Group align="flex-end" gap="sm" wrap="nowrap">
        <TextInput
          label="Cédula del servidor o del familiar"
          placeholder="Ej: 0801234567"
          autoFocus
          inputMode="numeric"
          {...contained}
          value={cedula}
          onChange={(e) => {
            setCedula(e.currentTarget.value)
            // La tarjeta del paciente anterior seguía a la vista, con sus
            // botones, aunque la cédula ya fuese otra.
            if (buscar.data || buscar.isError) limpiar()
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              handleBuscar()
            }
          }}
          flex={1}
          rightSection={
            cedula ? (
              <ActionIcon
                size="sm"
                variant="subtle"
                aria-label="Borrar la cédula"
                onClick={() => { setCedula(''); limpiar() }}
              >
                <IconX size={14} />
              </ActionIcon>
            ) : null
          }
        />
        <Button
          leftSection={<IconSearch size={16} />}
          loading={buscar.isPending}
          onClick={handleBuscar}
          // La altura del campo `contained`, para que no quede un escalón.
          h={48}
        >
          Buscar
        </Button>
      </Group>

      {buscar.isError && (
        <Alert icon={<IconInfoCircle size={14} />} color="red" variant="light">
          {/* El mensaje del servidor: «no se encontró» cuando es eso, y el
              motivo real cuando falla otra cosa. */}
          <Text size="xs">
            {getApiErrorMessage(buscar.error, 'No se pudo buscar al paciente.')}
          </Text>
        </Alert>
      )}

      {paciente && (
        <PacienteCard
          paciente={paciente}
          creandoHistoria={crearHistoria.isPending}
          onCrearHistoria={handleCrearHistoria}
          onElegir={(accion) => onElegir(paciente, accion)}
          turnoAbierto={turnoAbierto}
          onTomarTriaje={onTomarTriaje}
        />
      )}
    </Stack>
  )
}
