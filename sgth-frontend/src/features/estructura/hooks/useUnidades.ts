import { useQuery } from '@tanstack/react-query'
import type { UnidadAdministrativaParams } from '@/types/api'
import { estructuraService } from '../services/estructuraService'

export function useUnidades(params?: UnidadAdministrativaParams) {
  return useQuery({
    queryKey: ['unidades', params],
    queryFn: () => estructuraService.listarUnidades(params),
    staleTime: 1000 * 60 * 5,
  })
}
