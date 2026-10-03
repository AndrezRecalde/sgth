'use client'

import { useState } from 'react'
import { Stack, Card, Alert, Text, Button, Box } from '@mantine/core'
import {
  FlujoStepper,
  type PasoStepper,
} from '@/features/dispensario/components/FlujoStepper'
import { IconAlertTriangle, IconCheck } from '@tabler/icons-react'
import { BuscarPacienteForm } from '@/features/dispensario/components/BuscarPacienteForm'
import {
  SeleccionarAccionPaciente,
  type AccionPaciente,
} from '@/features/dispensario/components/SeleccionarAccionPaciente'
import { CrearTurnoForm } from '@/features/dispensario/components/CrearTurnoForm'
import { AtencionEnfermeriaForm } from '@/features/dispensario/components/AtencionEnfermeriaForm'
import { OfrecerTriajeInmediato } from '@/features/dispensario/components/OfrecerTriajeInmediato'
import { TriajeForm } from '@/features/dispensario/components/TriajeForm'
import type { PacienteEncontrado } from '@/features/dispensario/services/pacienteService'
import type { AgendaMedica } from '@/features/dispensario/services/agendaService'
import type { AtencionEnfermeria } from '@/features/dispensario/services/atencionEnfermeriaService'
import type { Triaje } from '@/features/dispensario/services/triajeService'

type Paso =
  | 'buscar'
  | 'elegir_accion'
  | 'crear_turno'
  | 'servicio_enfermeria'
  | 'ofrecer_triaje'
  | 'tomar_triaje'
  | 'finalizado'

function mapearPasoVisual(paso: Paso): PasoStepper {
  if (paso === 'buscar') return 'buscar'
  if (paso === 'elegir_accion') return 'elegir_accion'
  if (paso === 'finalizado') return 'confirmacion'
  return 'completar_datos'
}

export function EnfermeriaAtenderPacienteView() {
  const [paso, setPaso] = useState<Paso>('buscar')
  const [paciente, setPaciente] = useState<PacienteEncontrado | null>(null)
  const [agendaCreada, setAgendaCreada] = useState<AgendaMedica | null>(null)
  const [mensajeFinal, setMensajeFinal] = useState('')
  const [critico, setCritico] = useState(false)

  const handleReiniciar = () => {
    setPaso('buscar')
    setPaciente(null)
    setAgendaCreada(null)
    setMensajeFinal('')
    setCritico(false)
  }

  const handleElegirAccion = (accion: AccionPaciente) => {
    setPaso(accion === 'turno' ? 'crear_turno' : 'servicio_enfermeria')
  }

  const handleTurnoCreado = (agenda: AgendaMedica) => {
    setAgendaCreada(agenda)
    setPaso('ofrecer_triaje')
  }

  const handleServicioCreado = (atencion: AtencionEnfermeria) => {
    setMensajeFinal(`Atención ${atencion.folio} registrada`)
    setPaso('finalizado')
  }

  // El resultado se dice al terminar: antes el mensaje era el mismo para un
  // triaje normal que para uno crítico, que es justo el que hay que avisar.
  const handleTriajeCreado = (triaje: Triaje) => {
    const nivel = triaje.nivel_alerta
    const aviso = nivel === 'critico'
      ? ' — crítico: avise al profesional ahora'
      : nivel === 'atencion' ? ' — requiere atención' : ''
    setMensajeFinal(`Turno ${agendaCreada?.folio}: triaje registrado${aviso}`)
    setCritico(nivel === 'critico')
    setPaso('finalizado')
  }

  return (
    // El mismo ancho de lectura que tenía el `Container` (720px), pero
    // alineado a la izquierda: así el formulario arranca donde arranca el
    // título de la página, como en el resto del sistema.
    <Box maw={720}>
      <FlujoStepper pasoActual={mapearPasoVisual(paso)} />

      {paso === 'buscar' && (
        <BuscarPacienteForm
          onPacienteListo={(p) => {
            setPaciente(p)
            setPaso('elegir_accion')
          }}
        />
      )}

      {paso === 'elegir_accion' && paciente && (
        <SeleccionarAccionPaciente
          paciente={paciente}
          onElegir={handleElegirAccion}
          onVolver={handleReiniciar}
        />
      )}

      {paso === 'crear_turno' && paciente && (
        <CrearTurnoForm
          paciente={paciente}
          onCreado={handleTurnoCreado}
          onCancelar={() => setPaso('elegir_accion')}
        />
      )}

      {paso === 'servicio_enfermeria' && paciente && (
        <AtencionEnfermeriaForm
          paciente={paciente}
          onCreado={handleServicioCreado}
          onCancelar={() => setPaso('elegir_accion')}
        />
      )}

      {paso === 'ofrecer_triaje' && agendaCreada && (
        <OfrecerTriajeInmediato
          agenda={agendaCreada}
          onTomarTriaje={() => setPaso('tomar_triaje')}
          onTerminar={() => {
            setMensajeFinal(`Turno ${agendaCreada.folio} en cola de espera`)
            setPaso('finalizado')
          }}
        />
      )}

      {paso === 'tomar_triaje' && agendaCreada && (
        <TriajeForm
          turno={agendaCreada}
          onCreado={handleTriajeCreado}
          // El turno ya existe y sigue en la cola: «Cancelar» hacía pensar que
          // se cancelaba.
          textoCancelar="Tomar más tarde"
          onCancelar={() => {
            setMensajeFinal(`Turno ${agendaCreada.folio} en cola de espera`)
            setPaso('finalizado')
          }}
        />
      )}

      {paso === 'finalizado' && (
        <Card withBorder radius="lg" p="lg">
          <Stack gap="md" align="center">
            <Alert
              icon={critico ? <IconAlertTriangle size={16} /> : <IconCheck size={16} />}
              color={critico ? 'red' : 'emerald'}
              variant="light"
              w="100%"
            >
              <Text size="sm" fw={600} ta="center">
                {mensajeFinal}
              </Text>
            </Alert>
            {/* Era un `Card.Section` con `onClick` y `cursor: pointer`: se
                veía como un enlace pero no recibía el foco del teclado ni se
                anunciaba como algo pulsable. Una acción terciaria dentro de
                una tarjeta es `Button variant="subtle"` (regla 06). */}
            <Button variant="subtle" onClick={handleReiniciar}>
              Atender otro paciente
            </Button>
          </Stack>
        </Card>
      )}
    </Box>
  )
}
