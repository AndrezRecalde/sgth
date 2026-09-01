'use client'

import { useRouter } from 'next/navigation'
import { useQuery } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { IconCalendarOff } from '@tabler/icons-react'
import { agendaService } from '../services/agendaService'
import { useTurnosDelDia } from '../hooks/useAgenda'
import { AtencionMedicaPanel } from './AtencionMedicaPanel'
import { AtencionOdontologicaPanel } from './AtencionOdontologicaPanel'
import { DataState } from '@/components/ui'

type Especialidad = 'medica' | 'odontologica'

interface Props {
  folio: string
  especialidad: Especialidad
}

const PANEL = {
  medica: AtencionMedicaPanel,
  odontologica: AtencionOdontologicaPanel,
}

const RUTA_VUELTA: Record<Especialidad, string> = {
  medica: '/salud/consultas',
  odontologica: '/salud/odontologia',
}

/**
 * Ficha de atención de un turno. Consulta y odontología comparten pantalla:
 * solo cambian el panel que se monta y la lista a la que se vuelve al
 * finalizar.
 */
export function AtencionTurnoView({ folio, especialidad }: Props) {
  const router = useRouter()

  // El contador de la sala de espera alimenta la cabecera del panel.
  const { data: turnos = [] } = useTurnosDelDia()
  const totalEnEspera = turnos.filter(t =>
    ['en_espera', 'en_sala'].includes(t.estado)
  ).length

  const { data: turno, isLoading, error } = useQuery({
    queryKey: ['agenda', 'por-folio', folio],
    queryFn: () => agendaService.obtenerPorFolio(folio),
    retry: false,
    staleTime: 1000 * 30,
  })

  // Un folio inexistente no es un fallo: es un estado vacío con su propio
  // mensaje. Los demás errores —red caída, 500— sí son fallos, y antes se
  // mostraban todos como «turno no encontrado», que despistaba.
  const noEncontrado = isAxiosError(error) && error.response?.status === 404

  const Panel = PANEL[especialidad]

  return (
    <DataState
      loading={isLoading}
      error={noEncontrado ? undefined : error}
      empty={noEncontrado || !turno}
      emptyProps={{
        icon: IconCalendarOff,
        title: 'Turno no encontrado',
        description: `El turno ${folio} no existe o no está asignado a tu cuenta.`,
      }}
      skeletonRows={4}
    >
      {turno && (
        <Panel
          turno={turno}
          historiaClinicaId={turno.historia_clinica_id ?? 0}
          totalEnEspera={totalEnEspera}
          onFinalizar={() => router.push(RUTA_VUELTA[especialidad])}
        />
      )}
    </DataState>
  )
}
