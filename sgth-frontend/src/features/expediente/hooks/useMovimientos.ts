import { useQuery } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'

export function useMovimientos(servidorId: number | null) {
  return useQuery({
    queryKey: ['movimientos', servidorId],
    queryFn:  () => expedienteService.listarMovimientos(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
