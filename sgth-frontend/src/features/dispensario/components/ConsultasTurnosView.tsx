'use client'

import { useRouter } from 'next/navigation'
import { ROUTES } from '@/config/routes'
import { TurnosDelDiaTable } from './TurnosDelDiaTable'
import type { AgendaMedica } from '../services/agendaService'

export function ConsultasTurnosView() {
  const router = useRouter()

  // Atender y ver la consulta llevan a la misma ficha: la ficha decide qué
  // mostrar según el estado del turno.
  const abrirFicha = (turno: AgendaMedica) => {
    // Sin folio no hay ficha que abrir: la ruta se arma con él. La plantilla
    // que había antes se lo tragaba y navegaba a «/salud/consultas/undefined».
    if (!turno.folio) return
    router.push(ROUTES.SALUD.CONSULTA_TURNO(turno.folio))
  }

  return (
    <TurnosDelDiaTable
      onAtender={abrirFicha}
      onVerConsulta={abrirFicha}
    />
  )
}
