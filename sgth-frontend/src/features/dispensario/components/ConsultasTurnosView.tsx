'use client'

import { useRouter } from 'next/navigation'
import { ROUTES } from '@/config/routes'
import { TurnosDelDiaTable } from './TurnosDelDiaTable'
import { useAccionesTurno } from '../hooks/useAgenda'
import { TURNO_PENDIENTE } from '../constants/turnos'
import type { AgendaMedica } from '../services/agendaService'

export function ConsultasTurnosView() {
  const router = useRouter()
  const { enConsulta } = useAccionesTurno()

  // Atender y ver la consulta llevan a la misma ficha: la ficha decide qué
  // mostrar según el estado del turno.
  const abrirFicha = (turno: AgendaMedica) => {
    // Sin folio no hay ficha que abrir: la ruta se arma con él. La plantilla
    // que había antes se lo tragaba y navegaba a «/salud/consultas/undefined».
    if (!turno.folio) return
    router.push(ROUTES.SALUD.CONSULTA_TURNO(turno.folio))
  }

  // Atender deja el turno «en consulta»: es lo que ven la cola de
  // Enfermería, «Mi jornada» y el Panorama. Antes nadie escribía ese estado y
  // el paciente seguía contando como «esperando» con el médico delante.
  const atender = (turno: AgendaMedica) => {
    if (TURNO_PENDIENTE.includes(turno.estado)) enConsulta.mutate(turno.id)
    abrirFicha(turno)
  }

  return (
    <TurnosDelDiaTable
      onAtender={atender}
      onVerConsulta={abrirFicha}
    />
  )
}
