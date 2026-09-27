import { useQuery } from '@tanstack/react-query'
import { ausentismoSaludService } from '../services/ausentismoSaludService'

export function useAusentismoSalud(servidorId: number) {
  return useQuery({
    queryKey: ['ausentismo-salud', servidorId],
    queryFn: () => ausentismoSaludService.obtener(servidorId),
    staleTime: 1000 * 60 * 5,
  })
}
