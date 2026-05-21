import { useQuery } from '@tanstack/react-query'
import { estructuraService } from '../services/estructuraService'

export function useOrganigrama() {
  return useQuery({
    queryKey: ['organigrama'],
    queryFn: estructuraService.organigrama,
    staleTime: 1000 * 60 * 10,
  })
}
