import { useQuery } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'

export function useCargasFamiliares(servidorId: number | null) {
  return useQuery({
    queryKey: ['cargas-familiares', servidorId],
    queryFn:  () => expedienteService.listarCargasFamiliares(servidorId!),
    enabled:  servidorId !== null,
    staleTime: 1000 * 60 * 5,
  })
}
