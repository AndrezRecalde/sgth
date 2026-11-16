import { useQuery } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'

interface FiltrosVacacion {
  page?:                     number
  folio?:                    string
  estado?:                   string
  motivo?:                   string
  servidor_id?:              number
  unidad_administrativa_id?: number
  fecha_desde?:              string
  fecha_hasta?:              string
  per_page?:                 number
  equipo?:                   1
}

export function useVacaciones(filtros?: FiltrosVacacion) {
  return useQuery({
    queryKey: ['vacaciones', filtros],
    queryFn:  () => asistenciaService.vacaciones.listar(filtros),
    staleTime: 0,
  })
}

/**
 * El saldo de cada servidor de las unidades que dirige, para «Mi equipo». Se
 * pide al abrir su pestaña: calcularlo cuesta una consulta por persona.
 */
export function useSaldosDelEquipo() {
  return useQuery({
    queryKey: ['vacaciones', 'saldos-del-equipo'],
    queryFn:  () => asistenciaService.vacaciones.saldosDelEquipo(),
  })
}
