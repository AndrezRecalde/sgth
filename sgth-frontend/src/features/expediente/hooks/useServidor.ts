import { useQuery } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'

export function useServidor(id: number | null) {
  return useQuery({
    queryKey: ['servidor', id],
    queryFn: () => expedienteService.obtener(id!),
    enabled: !!id,
    staleTime: 1000 * 60 * 5,
  })
}
