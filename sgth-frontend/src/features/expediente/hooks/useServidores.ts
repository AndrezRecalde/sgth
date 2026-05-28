import { useQuery } from '@tanstack/react-query'
import type { ServidorParams } from '@/types/api'
import { expedienteService } from '../services/expedienteService'

export function useServidores(params?: ServidorParams) {
  return useQuery({
    queryKey: ['servidores', params],
    queryFn: () => expedienteService.listar(params),
    staleTime: 0,
  })
}
