import { useQuery } from '@tanstack/react-query'
import { subrogacionService } from '../services/subrogacionService'
import type { SubrogacionParams } from '@/types/api'

export function useSubrogacionesActivas(params?: SubrogacionParams) {
  return useQuery({
    queryKey: ['subrogaciones-activas', params],
    queryFn:  () => subrogacionService.listarActivas(params),
    staleTime: 1000 * 60,
  })
}
