import { useQuery } from '@tanstack/react-query'
import { estructuraService } from '../services/estructuraService'

export function useTiposUnidad() {
  return useQuery({
    queryKey: ['tipos-unidad'],
    queryFn: estructuraService.tiposUnidad,
    staleTime: 1000 * 60 * 30,
  })
}
