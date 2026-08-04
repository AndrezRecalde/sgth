import { useQuery } from '@tanstack/react-query'
import { actividadLaboralService } from '../services/actividadLaboralService'

export function useActividadLaboral(servidorId: number | null) {
  return useQuery({
    queryKey: ['actividad-laboral', servidorId],
    queryFn: () => actividadLaboralService.listar(servidorId!),
    enabled: servidorId !== null,
    staleTime: 1000 * 60,
  })
}
