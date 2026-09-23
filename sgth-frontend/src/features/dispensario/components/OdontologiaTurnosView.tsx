'use client'

import { useRouter } from 'next/navigation'
import { ROUTES } from '@/config/routes'
import { TurnosDelDiaTable } from './TurnosDelDiaTable'
import type { AgendaMedica } from '../services/agendaService'

export function OdontologiaTurnosView() {
  const router = useRouter()

  // Atender y ver la consulta llevan a la misma ficha: el odontograma
  // decide qué mostrar según el estado del turno.
  const abrirFicha = (turno: AgendaMedica) => {
    // Sin folio no hay ficha que abrir: la ruta se arma con él. La plantilla
    // que había antes se lo tragaba y navegaba a «/salud/odontologia/undefined».
    if (!turno.folio) return
    router.push(ROUTES.SALUD.ODONTOLOGIA_TURNO(turno.folio))
  }

  return (
    <TurnosDelDiaTable
      onAtender={abrirFicha}
      onVerConsulta={abrirFicha}
    />
  )
}
