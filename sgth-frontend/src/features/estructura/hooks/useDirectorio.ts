import { useQuery } from '@tanstack/react-query'
import type { ExtensionTelefonicaParams } from '@/types/api'
import { estructuraService } from '../services/estructuraService'

export function useDirectorio(params?: ExtensionTelefonicaParams) {
  return useQuery({
    queryKey: ['directorio', params],
    queryFn: () => estructuraService.directorio(params),
    staleTime: 1000 * 60 * 5,
  })
}
