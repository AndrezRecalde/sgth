'use client'

import { useState } from 'react'
import { Stack, TextInput, Button, Alert, Text, ActionIcon, Group } from '@mantine/core'
import { IconSearch, IconX, IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { getApiErrorMessage } from '@/types/api'
import { hoyIso } from '@/lib/fecha'
import { useBuscarPaciente, useBuscarPacientesPorNombre } from '../hooks/usePaciente'
import { useCrearHistoriaClinica } from '../hooks/useHistoriaClinica'
import { useColaTurnos } from '../hooks/useAgenda'
import { TURNO_PENDIENTE } from '../constants/turnos'
import { PacienteCard, type AccionPaciente } from './PacienteCard'
import { ResultadosPorNombre } from './ResultadosPorNombre'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  onElegir:       (paciente: PacienteEncontrado, accion: AccionPaciente) => void
  onTomarTriaje?: (turno: AgendaMedica) => void
}

/** Abierto hoy: esperando, listo para pasar o ya con el profesional. */
const ABIERTOS = [...TURNO_PENDIENTE, 'en_consulta']

/** Solo dígitos: es una cédula. Cualquier otra cosa se busca como nombre. */
const esCedula = (texto: string) => /^\d+$/.test(texto)

/**
 * Buscar al paciente por cédula o por nombre, en el mismo campo.
 *
 * Solo admitía cédula, y con un familiar menor quien lo trae muchas veces no
 * se la sabe. Si lo escrito son dígitos se busca la cédula; si no, el nombre o
 * los apellidos, y se elige de la lista.
 *
 * El cursor está en el campo al entrar y al volver de atender a alguien: no
 * hace falta tocar el ratón para empezar con el siguiente.
 */
export function BuscarPacienteForm({ onElegir, onTomarTriaje }: Props) {
  const contained = useContainedInput()
  const [texto, setTexto] = useState('')
  const [elegido, setElegido] = useState<PacienteEncontrado | null>(null)
  // La historia recién creada, para no tener que volver a buscar.
  const [historiaCreada, setHistoriaCreada] = useState<number | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)

  const porCedula = useBuscarPaciente()
  const porNombre = useBuscarPacientesPorNombre()
  const crearHistoria = useCrearHistoriaClinica()
  const { data: cola } = useColaTurnos({ fecha: hoyIso(), per_page: 200 })

  const encontrado = elegido ?? porCedula.data
  const paciente = encontrado && historiaCreada
    ? { ...encontrado, tiene_historia_clinica: true, historia_clinica_id: historiaCreada }
    : encontrado

  const turnoAbierto = paciente && cola?.data.find((t) =>
    ABIERTOS.includes(t.estado) && (paciente.tipo === 'servidor'
      ? t.servidor_id === paciente.id
      : t.carga_familiar_id === paciente.id),
  )

  const limpiar = () => {
    porCedula.reset()
    porNombre.reset()
    setElegido(null)
    setHistoriaCreada(null)
    setAviso(null)
  }

  const handleBuscar = () => {
    const limpio = texto.trim()
    if (!limpio) return
    limpiar()
    if (esCedula(limpio)) porCedula.mutate(limpio)
    else if (limpio.length < 3) setAviso('Escriba al menos tres letras del nombre o los apellidos.')
    else porNombre.mutate(limpio)
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

  const error = porCedula.error ?? porNombre.error
  const sinCoincidencias = porNombre.data?.length === 0

  return (
    <Stack gap="md">
      <Group align="flex-end" gap="sm" wrap="nowrap">
        <TextInput
          label="Cédula o apellidos del paciente"
          placeholder="Ej: 0801234567 o Arroyo Vera"
          autoFocus
          {...contained}
          value={texto}
          onChange={(e) => {
            setTexto(e.currentTarget.value)
            // Lo encontrado con la búsqueda anterior se va: seguía a la vista,
            // con sus botones, aunque lo escrito ya fuese otro.
            if (paciente || porNombre.data || error || aviso) limpiar()
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              handleBuscar()
            }
          }}
          flex={1}
          rightSection={
            texto ? (
              <ActionIcon
                size="sm"
                variant="subtle"
                aria-label="Borrar la búsqueda"
                onClick={() => { setTexto(''); limpiar() }}
              >
                <IconX size={14} />
              </ActionIcon>
            ) : null
          }
        />
        <Button
          leftSection={<IconSearch size={16} />}
          loading={porCedula.isPending || porNombre.isPending}
          onClick={handleBuscar}
          // La altura del campo `contained`, para que no quede un escalón.
          h={48}
        >
          Buscar
        </Button>
      </Group>

      {(error || aviso || sinCoincidencias) && (
        <Alert icon={<IconInfoCircle size={14} />} color={error ? 'red' : 'amber'} variant="light">
          {/* El mensaje del servidor: «no se encontró» cuando es eso, y el
              motivo real cuando falla otra cosa. */}
          <Text size="xs">
            {aviso
              ?? (sinCoincidencias
                ? 'Nadie con ese nombre. Si es un familiar, debe estar registrado como carga familiar en el Expediente del servidor.'
                : getApiErrorMessage(error, 'No se pudo buscar al paciente.'))}
          </Text>
        </Alert>
      )}

      {!paciente && !!porNombre.data?.length && (
        <ResultadosPorNombre pacientes={porNombre.data} onElegir={setElegido} />
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
