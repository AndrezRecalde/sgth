'use client'

import { useState } from 'react'
import { Grid, Stack } from '@mantine/core'
import { BuscarPacienteForm } from './BuscarPacienteForm'
import { CrearTurnoForm } from './CrearTurnoForm'
import { AtencionEnfermeriaForm } from './AtencionEnfermeriaForm'
import { OfrecerTriajeInmediato } from './OfrecerTriajeInmediato'
import { TriajeForm } from './TriajeForm'
import { CierreAtencion } from './CierreAtencion'
import { HoyEnEnfermeria } from './HoyEnEnfermeria'
import type { AccionPaciente } from './PacienteCard'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AgendaMedica } from '../services/agendaService'
import type { AtencionEnfermeria } from '../services/atencionEnfermeriaService'
import type { Triaje } from '../services/triajeService'

type Paso =
  | { tipo: 'buscar' }
  | { tipo: 'crear_turno'; paciente: PacienteEncontrado }
  | { tipo: 'servicio'; paciente: PacienteEncontrado }
  | { tipo: 'ofrecer_triaje'; turno: AgendaMedica }
  | { tipo: 'triaje'; turno: AgendaMedica; recienCreado: boolean }
  | { tipo: 'fin'; mensaje: string; critico: boolean }

/**
 * Atender paciente: buscar, decidir y registrar, con lo que espera a
 * Enfermería al lado.
 *
 * Dos columnas desde pantallas medianas. A la izquierda el flujo, con el
 * mismo ancho de lectura que tenía y alineado con el título; a la derecha,
 * donde antes había media pantalla vacía, los pendientes de triaje. En el
 * teléfono el panel va debajo: lo primero sigue siendo buscar.
 *
 * Sin el indicador de pasos de antes: tenía cuatro pasos para dos o tres
 * decisiones y «Completar datos» abarcaba cosas distintas. La cabecera del
 * paciente en cada formulario ya dice dónde se está.
 */
export function EnfermeriaAtenderPacienteView() {
  const [paso, setPaso] = useState<Paso>({ tipo: 'buscar' })
  const reiniciar = () => setPaso({ tipo: 'buscar' })

  const elegir = (paciente: PacienteEncontrado, accion: AccionPaciente) =>
    setPaso(accion === 'turno'
      ? { tipo: 'crear_turno', paciente }
      : { tipo: 'servicio', paciente })

  const terminar = (mensaje: string, critico = false) =>
    setPaso({ tipo: 'fin', mensaje, critico })

  // El resultado se dice al terminar: un triaje crítico es justo el que hay
  // que avisar.
  const triajeRegistrado = (turno: AgendaMedica, triaje: Triaje) => {
    const nivel = triaje.nivel_alerta
    const aviso = nivel === 'critico'
      ? ' — crítico: avise al profesional ahora'
      : nivel === 'atencion' ? ' — requiere atención' : ''
    terminar(`Turno ${turno.folio}: triaje registrado${aviso}`, nivel === 'critico')
  }

  const tomarTriaje = (turno: AgendaMedica) =>
    setPaso({ tipo: 'triaje', turno, recienCreado: false })

  return (
    <Grid gap="lg">
      <Grid.Col span={{ base: 12, md: 7 }}>
        <Stack gap="md" maw={720}>
          {paso.tipo === 'buscar' && (
            <BuscarPacienteForm onElegir={elegir} onTomarTriaje={tomarTriaje} />
          )}

          {paso.tipo === 'crear_turno' && (
            <CrearTurnoForm
              paciente={paso.paciente}
              onCreado={(turno) => setPaso({ tipo: 'ofrecer_triaje', turno })}
              onCancelar={reiniciar}
            />
          )}

          {paso.tipo === 'servicio' && (
            <AtencionEnfermeriaForm
              paciente={paso.paciente}
              onCreado={(a: AtencionEnfermeria) => terminar(`Servicio ${a.folio} registrado`)}
              onCancelar={reiniciar}
            />
          )}

          {paso.tipo === 'ofrecer_triaje' && (
            <OfrecerTriajeInmediato
              agenda={paso.turno}
              onTomarTriaje={() => setPaso({ tipo: 'triaje', turno: paso.turno, recienCreado: true })}
              onTerminar={() => terminar(`Turno ${paso.turno.folio} en cola de espera`)}
            />
          )}

          {paso.tipo === 'triaje' && (
            <TriajeForm
              // Uno distinto por turno: los valores iniciales se fijan al montar.
              key={paso.turno.id}
              turno={paso.turno}
              onCreado={(triaje) => triajeRegistrado(paso.turno, triaje)}
              // El turno ya existe y sigue en la cola: «Cancelar» hacía pensar
              // que se cancelaba.
              textoCancelar={paso.recienCreado ? 'Tomar más tarde' : 'Volver'}
              onCancelar={() => paso.recienCreado
                ? terminar(`Turno ${paso.turno.folio} en cola de espera`)
                : reiniciar()}
            />
          )}

          {paso.tipo === 'fin' && (
            <CierreAtencion mensaje={paso.mensaje} critico={paso.critico} onOtro={reiniciar} />
          )}
        </Stack>
      </Grid.Col>

      <Grid.Col span={{ base: 12, md: 5 }}>
        <HoyEnEnfermeria onTomarTriaje={tomarTriaje} />
      </Grid.Col>
    </Grid>
  )
}
