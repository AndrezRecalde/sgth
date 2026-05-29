import { useQuery } from '@tanstack/react-query'
import { asistenciaService } from '../services/asistenciaService'

export function useVacaciones(params?: Record<string, string>) {
  return useQuery({
    queryKey: ['vacaciones', params],
    queryFn:  () => asistenciaService.vacaciones.listar(params),
    staleTime: 0,
  })
}
