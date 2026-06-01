import { useQuery } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'

export function usePeriodosVacaciones(servidorId: number | null) {
  return useQuery({
    queryKey: ['periodos-vacaciones', servidorId],
    queryFn:  () =>
      asistenciaService.periodos.resumen(servidorId!),
    enabled:  !!servidorId,
    staleTime: 0,
  })
}
