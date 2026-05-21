import { useQuery } from '@tanstack/react-query'
import type { PuestoParams } from '@/types/api'
import { estructuraService } from '../services/estructuraService'

export function usePuestos(params?: PuestoParams) {
  return useQuery({
    queryKey: ['puestos', params],
    queryFn: () => estructuraService.listarPuestos(params),
    staleTime: 1000 * 60 * 5,
  })
}
