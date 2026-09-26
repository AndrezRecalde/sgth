import { useQuery } from '@tanstack/react-query'
import type { ServidorParams } from '@/types/api'
import { servidorService } from '../services/servidorService'

export function useServidores(params?: ServidorParams) {
  return useQuery({
    queryKey: ['servidores', params],
    queryFn: () => servidorService.listar(params),
    staleTime: 0,
  })
}
