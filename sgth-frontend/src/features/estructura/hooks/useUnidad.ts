import { useQuery } from '@tanstack/react-query'
import { estructuraService } from '../services/estructuraService'

export function useUnidad(id: number | null) {
  return useQuery({
    queryKey: ['unidad', id],
    queryFn: () => estructuraService.obtenerUnidad(id!),
    enabled: id !== null,
    staleTime: 1000 * 60 * 5,
  })
}
