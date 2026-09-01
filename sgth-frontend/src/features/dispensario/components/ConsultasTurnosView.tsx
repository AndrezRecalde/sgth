'use client'

import { useRouter } from 'next/navigation'
import { TurnosDelDiaTable } from './TurnosDelDiaTable'
import type { AgendaMedica } from '../services/agendaService'

export function ConsultasTurnosView() {
  const router = useRouter()

  // Atender y ver la consulta llevan a la misma ficha: la ficha decide qué
  // mostrar según el estado del turno.
  const abrirFicha = (turno: AgendaMedica) => {
    router.push(`/salud/consultas/${turno.folio}`)
  }

  return (
    <TurnosDelDiaTable
      onAtender={abrirFicha}
      onVerConsulta={abrirFicha}
    />
  )
}
