import { useQuery } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'

export function useDeclaraciones(servidorId: number | null) {
  return useQuery({
    queryKey: ['declaraciones', servidorId],
    queryFn:  () => expedienteService.listarDeclaraciones(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
