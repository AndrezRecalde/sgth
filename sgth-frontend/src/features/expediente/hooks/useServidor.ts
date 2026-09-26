import { useQuery } from '@tanstack/react-query'
import { servidorService } from '../services/servidorService'

export function useServidor(id: number | null) {
  return useQuery({
    queryKey: ['servidor', id],
    queryFn: () => servidorService.obtener(id!),
    enabled: !!id,
    staleTime: 1000 * 60 * 5,
  })
}
