import { useQuery } from '@tanstack/react-query'
import type { CargoParams } from '@/types/api'
import { cargoService } from '../services/cargoService'

export function useCargos(params?: CargoParams) {
  return useQuery({
    queryKey: ['cargos', params],
    queryFn: () => cargoService.listar(params),
    staleTime: 1000 * 60 * 10,
  })
}
