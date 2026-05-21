import { useQuery } from '@tanstack/react-query'
import { catalogoService } from '../services/catalogoService'

export function useCantones(provinciaId: number | null) {
  return useQuery({
    queryKey: ['cantones', provinciaId],
    queryFn: () => catalogoService.cantones(provinciaId!),
    enabled: !!provinciaId,
    staleTime: 1000 * 60 * 30,
  })
}
